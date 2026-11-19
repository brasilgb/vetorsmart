<?php

namespace App\Services\Websites;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Logo and main image of a company site, kept on the private local disk under websites/{tenant_id}/. The folder comes
 * from the company of the request, never from the payload, so a file always belongs to its company. Every upload is
 * decoded and re-encoded as WebP with GD: metadata (EXIF, location) is dropped, oversized images are reduced, and a
 * file that is not a real image never reaches the disk. File names are random and served only when referenced.
 */
class WebsiteMedia
{
    public const string DISK = 'local';

    /**
     * Largest width kept for each kind of image.
     *
     * @var array<string, int>
     */
    public const array MAX_WIDTH = ['logo' => 600, 'hero' => 1600];

    public const string FILE_PATTERN = '(?:logo|hero)-[a-z0-9]{40}\.webp';

    /**
     * @return array{file: string, width: int, height: int}
     */
    public function store(Tenant $tenant, string $kind, UploadedFile $upload): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($upload->getRealPath()));

        if ($source === false) {
            throw ValidationException::withMessages(['image' => 'Não foi possível ler a imagem. Envie um arquivo JPG, PNG ou WebP válido.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $maxWidth = self::MAX_WIDTH[$kind] ?? 1600;

        if ($width > $maxWidth) {
            $height = max(1, (int) round($height * $maxWidth / $width));
            $width = $maxWidth;
        }

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        ob_start();
        imagewebp($image, null, 82);
        $bytes = (string) ob_get_clean();

        $file = $kind.'-'.Str::lower(Str::random(40)).'.webp';
        Storage::disk(self::DISK)->put($this->path($tenant, $file), $bytes);

        return ['file' => $file, 'width' => $width, 'height' => $height];
    }

    /**
     * Path of a company file on the disk. The folder is always the company's own.
     */
    public function path(Tenant|int $tenant, string $file): string
    {
        return 'websites/'.($tenant instanceof Tenant ? $tenant->getKey() : $tenant).'/'.$file;
    }

    public function isValidName(string $file): bool
    {
        return preg_match('/^'.self::FILE_PATTERN.'$/', $file) === 1;
    }

    /**
     * Whether the given content (a draft or a published version) references this file.
     *
     * @param  array<string, mixed>  $content
     */
    public function isReferenced(array $content, string $file): bool
    {
        foreach (['logo', 'hero'] as $kind) {
            if (is_array($content[$kind] ?? null) && ($content[$kind]['file'] ?? null) === $file) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove the company's images that neither the draft nor the published version uses any more.
     *
     * @param  list<array<string, mixed>>  $contents
     */
    public function prune(Tenant $tenant, array $contents): void
    {
        $disk = Storage::disk(self::DISK);

        foreach ($disk->files('websites/'.$tenant->getKey()) as $path) {
            $file = basename($path);
            $used = false;

            foreach ($contents as $content) {
                $used = $used || $this->isReferenced($content, $file);
            }

            if (! $used) {
                $disk->delete($path);
            }
        }
    }
}
