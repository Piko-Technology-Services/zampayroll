<?php

namespace App\Services\Compliance;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Prepares a signature image: a drawn signature (PNG from the signature pad) or an uploaded photo/scan.
 * Uploads get their paper background removed with GD (light pixels become transparent, ink keeps its shading),
 * and both are trimmed to the ink. The image alone is not treated as proof: the signing record also holds the
 * signed-in account, time, IP, document fingerprint and consent statement.
 */
class SignatureImage
{
    public static function fromDataUrl(string $dataUrl): string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw ValidationException::withMessages(['signature' => 'Draw your signature in the box first.']);
        }
        $bytes = base64_decode($m[1], true);
        if (! $bytes || strlen($bytes) > 1_500_000 || (@getimagesizefromstring($bytes)[2] ?? null) !== IMAGETYPE_PNG) {
            throw ValidationException::withMessages(['signature' => 'That signature could not be read. Please draw it again.']);
        }
        $img = imagecreatefromstring($bytes);

        return self::finish($img, fn ($rgba) => $rgba['a'] < 100 && $rgba['l'] < 200);
    }

    public static function fromUpload(UploadedFile $file): string
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true) || $file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['signature_file' => 'Upload a PNG or JPG image of your signature, up to 5 MB.']);
        }
        $img = imagecreatefromstring(file_get_contents($file->getRealPath()));
        $img = self::downscale($img, 1400);
        $bg = self::backgroundLuminance($img);
        $threshold = max(60, $bg - 45);

        // Remove the paper: anything close to the background brightness becomes transparent.
        $w = imagesx($img);
        $h = imagesy($img);
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                $l = 0.299 * $c['red'] + 0.587 * $c['green'] + 0.114 * $c['blue'];
                if ($l >= $threshold) {
                    continue;
                }
                $alpha = (int) round(127 * max(0, min(1, $l / $threshold)) * 0.85);
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, (int) ($c['red'] * .6), (int) ($c['green'] * .6), (int) ($c['blue'] * .6), $alpha));
            }
        }
        imagedestroy($img);

        return self::finish($out, fn ($rgba) => $rgba['a'] < 100);
    }

    private static function downscale(\GdImage $img, int $max): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= $max && $h <= $max) {
            return $img;
        }
        $r = $max / max($w, $h);
        $s = imagescale($img, (int) round($w * $r), (int) round($h * $r));
        imagedestroy($img);

        return $s;
    }

    /** Median brightness of the border pixels: a good estimate of the paper colour. */
    private static function backgroundLuminance(\GdImage $img): float
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $vals = [];
        $step = max(1, (int) (($w + $h) / 200));
        for ($x = 0; $x < $w; $x += $step) {
            foreach ([0, $h - 1] as $y) {
                $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                $vals[] = 0.299 * $c['red'] + 0.587 * $c['green'] + 0.114 * $c['blue'];
            }
        }
        for ($y = 0; $y < $h; $y += $step) {
            foreach ([0, $w - 1] as $x) {
                $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                $vals[] = 0.299 * $c['red'] + 0.587 * $c['green'] + 0.114 * $c['blue'];
            }
        }
        sort($vals);

        return $vals ? $vals[(int) floor(count($vals) / 2)] : 255;
    }

    /** Trim to the ink with a small margin and check that something was actually signed. */
    private static function finish(\GdImage $img, callable $isInk): string
    {
        $w = imagesx($img);
        $h = imagesy($img);
        [$minX, $minY, $maxX, $maxY, $ink] = [$w, $h, -1, -1, 0];
        $step = 1;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                $rgba = ['a' => $c['alpha'], 'l' => 0.299 * $c['red'] + 0.587 * $c['green'] + 0.114 * $c['blue']];
                if ($isInk($rgba)) {
                    $ink++;
                    $minX = min($minX, $x);
                    $minY = min($minY, $y);
                    $maxX = max($maxX, $x);
                    $maxY = max($maxY, $y);
                }
            }
        }
        if ($ink < 40 || $maxX - $minX < 20) {
            throw ValidationException::withMessages(['signature' => 'We could not find a signature in that image. Please try again.']);
        }
        $pad = 8;
        $minX = max(0, $minX - $pad);
        $minY = max(0, $minY - $pad);
        $cw = min($w - $minX, $maxX - $minX + 2 * $pad);
        $ch = min($h - $minY, $maxY - $minY + 2 * $pad);
        $crop = imagecreatetruecolor($cw, $ch);
        imagealphablending($crop, false);
        imagesavealpha($crop, true);
        imagefill($crop, 0, 0, imagecolorallocatealpha($crop, 255, 255, 255, 127));
        imagecopy($crop, $img, 0, 0, $minX, $minY, $cw, $ch);
        imagedestroy($img);
        ob_start();
        imagepng($crop);
        imagedestroy($crop);

        return ob_get_clean();
    }
}
