<?php

namespace App\Services\Compliance;

use App\Models\Company;
use App\Models\Compliance\Letterhead;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the pieces of a letterhead for PDF and Word output, and prepares uploaded letterhead files.
 */
class Letterheads
{
    /** Company logo as raw bytes (logos live on the public disk today). */
    public static function logoBytes(?Company $company): ?string
    {
        $path = $company?->logo_path;
        if (! $path) {
            return null;
        }
        $path = preg_replace('#^/?storage/#', '', $path);
        foreach (['public', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                $bytes = Storage::disk($disk)->get($path);

                return @getimagesizefromstring($bytes) ? $bytes : null;
            }
        }
        $abs = public_path($path);

        return is_file($abs) && @getimagesize($abs) ? file_get_contents($abs) : null;
    }

    public static function contactLine(Company $company): string
    {
        return collect([$company->phone, $company->email, $company->website])->filter()->implode('  ·  ');
    }

    public static function addressLine(Company $company): string
    {
        return collect([$company->address, $company->city, $company->province])->filter()->implode(', ');
    }

    public static function registrationLine(Company $company): string
    {
        return collect([
            $company->tpin ? 'TPIN ' . $company->tpin : null,
            $company->pacra_registration_number ? 'PACRA Reg. ' . $company->pacra_registration_number : null,
            $company->napsa_employer_number ? 'NAPSA ' . $company->napsa_employer_number : null,
        ])->filter()->implode('  ·  ');
    }

    /** CSS + fixed header/footer markup for dompdf. $lh may be null (plain generated look from the company profile). */
    public static function pdfFrame(?Letterhead $lh, Company $company, ?string $footerNote = null): array
    {
        $s = $lh ? $lh->allSettings() : Letterhead::DEFAULTS;
        $mt = (float) $s['margin_top'];
        $mb = (float) $s['margin_bottom'];
        $ml = (float) $s['margin_left'];
        $mr = (float) $s['margin_right'];
        $hh = (float) $s['header_height'];
        $fh = (float) $s['footer_height'];
        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $s['accent']) ? $s['accent'] : '#0B3D2E';
        $font = array_key_exists($s['font'], Letterhead::FONTS) ? $s['font'] : 'DejaVu Sans';
        $size = max(8, min(14, (float) $s['font_size']));
        $pageNums = (bool) $s['page_numbers'];

        $css = "@page { margin: " . ($hh + $mt) . "mm {$mr}mm " . ($fh + $mb) . "mm {$ml}mm; }
body { font-family: '{$font}', 'DejaVu Sans', sans-serif; font-size: {$size}pt; line-height: 1.45; color: #1b1b1b; }
h1 { font-size: " . ($size + 5) . "pt; color: {$accent}; margin: 0 0 10pt; letter-spacing: .3pt; }
h2 { font-size: " . ($size + 1.5) . "pt; color: {$accent}; margin: 14pt 0 5pt; }
h3 { font-size: " . ($size + .5) . "pt; margin: 10pt 0 4pt; }
p { margin: 0 0 7pt; } ul, ol { margin: 0 0 7pt 16pt; padding: 0; } li { margin: 0 0 3pt; }
table { border-collapse: collapse; width: 100%; margin: 0 0 8pt; } td, th { border: 1px solid #c9d3cc; padding: 4pt 6pt; vertical-align: top; text-align: left; }
th { background: #eef3ef; }
hr { border: 0; border-top: 1px solid #c9d3cc; }
.ph-missing { background: #fff3c4; color: #7a5600; }
#lh-head { position: fixed; top: -" . ($hh + $mt) . "mm; left: -{$ml}mm; right: -{$mr}mm; height: {$hh}mm; }
#lh-foot { position: fixed; bottom: -" . ($fh + $mb) . "mm; left: -{$ml}mm; right: -{$mr}mm; height: {$fh}mm; }
.pagenum:before { content: counter(page); } .pagecount:before { content: counter(pages); }
.wm { position: fixed; top: 35%; left: 0; right: 0; text-align: center; font-size: 70pt; color: #e8ece9; transform: rotate(-30deg); z-index: -1; }";

        $pager = ''; // page numbers are drawn by DocumentPdf after layout ("Page X of Y")
        $note = $footerNote ? e($footerNote) : '';

        if ($lh && $lh->mode === 'custom' && $lh->header_path) {
            $img = Vault::dataUri($lh->header_path);
            $head = '<div id="lh-head"><img src="' . $img . '" style="width:100%;max-height:' . $hh . 'mm"></div>';
            $footImg = $lh->footer_path ? Vault::dataUri($lh->footer_path) : null;
            $foot = '<div id="lh-foot">' . ($footImg ? '<img src="' . $footImg . '" style="width:100%;max-height:' . $fh . 'mm;position:absolute;bottom:0;left:0">' : '')
                . '<div style="position:absolute;bottom:' . ($footImg ? $fh - 1 : 4) . 'mm;left:' . $ml . 'mm;right:' . $mr . 'mm;font-size:7.5pt;color:#55615a">'
                . '<span>' . $note . '</span><span style="float:right">' . $pager . '</span></div></div>';
        } else {
            $logo = self::logoBytes($company);
            $logoUri = $logo ? 'data:' . ((new \finfo(FILEINFO_MIME_TYPE))->buffer($logo) ?: 'image/png') . ';base64,' . base64_encode($logo) : null;
            $pos = in_array($s['logo_position'], ['left', 'center', 'right'], true) ? $s['logo_position'] : 'left';
            $logoHtml = $logoUri ? '<img src="' . $logoUri . '" style="max-height:' . max(10, $hh - 14) . 'mm;max-width:45mm">' : '';
            $text = '<div style="font-size:15pt;font-weight:bold;color:' . $accent . '">' . e($company->name) . '</div>'
                . '<div style="font-size:8pt;color:#45524a;margin-top:1mm">' . e(self::addressLine($company)) . '</div>'
                . '<div style="font-size:8pt;color:#45524a">' . e(self::contactLine($company)) . '</div>';
            $inner = $pos === 'center'
                ? '<div style="text-align:center">' . ($logoHtml ? $logoHtml . '<br>' : '') . $text . '</div>'
                : '<table style="width:100%;border:0;margin:0"><tr>'
                    . ($pos === 'left' && $logoHtml ? '<td style="border:0;width:48mm;padding:0;vertical-align:middle">' . $logoHtml . '</td>' : '')
                    . '<td style="border:0;padding:0;vertical-align:middle;text-align:' . ($pos === 'right' || ! $logoHtml ? 'left' : 'right') . '">' . $text . '</td>'
                    . ($pos === 'right' && $logoHtml ? '<td style="border:0;width:48mm;padding:0;text-align:right;vertical-align:middle">' . $logoHtml . '</td>' : '')
                    . '</tr></table>';
            $head = '<div id="lh-head"><div style="padding:8mm ' . $mr . 'mm 0 ' . $ml . 'mm;height:' . ($hh - 10) . 'mm;border-bottom:2px solid ' . $accent . '">' . $inner . '</div></div>';
            $reg = $s['show_registration'] ? e(self::registrationLine($company)) : '';
            $foot = '<div id="lh-foot"><div style="margin:0 ' . $mr . 'mm 0 ' . $ml . 'mm;border-top:1px solid #c9d3cc;padding-top:2mm;font-size:7.5pt;color:#55615a">'
                . '<span>' . $reg . ($reg && $note ? '  ·  ' : '') . $note . '</span><span style="float:right">' . $pager . '</span></div></div>';
        }

        return ['css' => $css, 'head' => $head, 'foot' => $foot, 'page_numbers' => $pageNums,
            'pager_y_mm' => $lh && $lh->mode === 'custom' && $lh->footer_path ? 297 - $fh - 4 : 297 - $fh + 2.2, 'pager_right_mm' => $mr];
    }

    /**
     * Store an uploaded header or footer. Images are kept as they are; a PDF is converted to an image of its first page
     * when the server has `pdftoppm` (poppler) installed, otherwise the caller is told to upload PNG or JPG.
     *
     * @return array{path:?string, source:?string, error:?string}
     */
    public static function storeUpload(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        if (in_array($ext, ['png', 'jpg', 'jpeg'], true) && in_array($mime, ['image/png', 'image/jpeg'], true) && @getimagesize($file->getRealPath())) {
            return ['path' => Vault::putUpload($file, 'letterheads', $ext === 'jpeg' ? 'jpg' : $ext), 'source' => null, 'error' => null];
        }
        if ($ext === 'pdf' && $mime === 'application/pdf') {
            $source = Vault::putUpload($file, 'letterheads', 'pdf');
            $png = self::pdfFirstPageToPng(Vault::absolute($source));
            if ($png) {
                return ['path' => Vault::putContents($png, 'letterheads', 'png'), 'source' => $source, 'error' => null];
            }

            return ['path' => null, 'source' => $source, 'error' => 'We saved your PDF, but this server cannot turn a PDF into an image. Please upload the header (and footer) as PNG or JPG images.'];
        }

        return ['path' => null, 'source' => null, 'error' => 'Upload a PNG, JPG or PDF file.'];
    }

    private static function pdfFirstPageToPng(string $pdf): ?string
    {
        $bin = trim((string) @shell_exec('command -v pdftoppm 2>/dev/null'));
        if ($bin === '' || ! function_exists('proc_open')) {
            return null;
        }
        $out = tempnam(sys_get_temp_dir(), 'lh');
        $cmd = escapeshellcmd($bin) . ' -png -r 150 -f 1 -l 1 -singlefile ' . escapeshellarg($pdf) . ' ' . escapeshellarg($out);
        @exec($cmd, $o, $code);
        $file = $out . '.png';
        $bytes = $code === 0 && is_file($file) ? file_get_contents($file) : null;
        @unlink($file);
        @unlink($out);

        return $bytes;
    }
}
