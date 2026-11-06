<?php

namespace App\Services\Compliance;

use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Private file storage for the Compliance Centre. Files live on the private "local" disk
 * (storage/app/private) under compliance/{company}/..., with random names: never in the public web root.
 * Files are only served through controllers that check the tenant and the user's rights.
 */
class Vault
{
    public const DISK = 'local';

    public static function disk()
    {
        return Storage::disk(self::DISK);
    }

    public static function dir(string $area, ?int $companyId = null): string
    {
        $cid = (int) ($companyId ?? Tenancy::companyId());
        abort_unless($cid > 0, 403);

        return "compliance/{$cid}/{$area}";
    }

    public static function putUpload(UploadedFile $file, string $area, string $ext): string
    {
        $name = Str::uuid()->toString() . '.' . strtolower($ext);

        return $file->storeAs(self::dir($area), $name, self::DISK);
    }

    public static function putContents(string $contents, string $area, string $ext, ?int $companyId = null): string
    {
        $path = self::dir($area, $companyId) . '/' . Str::uuid()->toString() . '.' . strtolower($ext);
        self::disk()->put($path, $contents);

        return $path;
    }

    public static function get(?string $path): ?string
    {
        if (! $path || str_contains($path, '..') || ! str_starts_with($path, 'compliance/')) {
            return null;
        }

        return self::disk()->exists($path) ? self::disk()->get($path) : null;
    }

    public static function absolute(string $path): string
    {
        return self::disk()->path($path);
    }

    public static function dataUri(?string $path): ?string
    {
        $bytes = self::get($path);
        if ($bytes === null) {
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /** Make sure a path belongs to this company's area before serving it. */
    public static function belongsTo(string $path, int $companyId): bool
    {
        return str_starts_with($path, "compliance/{$companyId}/") && ! str_contains($path, '..');
    }
}
