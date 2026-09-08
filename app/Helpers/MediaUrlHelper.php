<?php

namespace App\Helpers;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

/**
 * Bumubuo ng public URL para sa isang naka-imbak na larawan.
 *
 * BAKIT HINDI NA LANG `Storage::disk('public')->url()`?
 *
 * Dahil sa Cloudinary driver, HINDI iyon pagbuo ng string. Ganito ang
 * adapter ng cloudinary-labs/cloudinary-laravel:
 *
 *     public function getUrl(string $path): string
 *     {
 *         [$id, $type] = $this->prepareResource($path);
 *         return $this->cloudinary->adminApi()->asset($id, [...])->offsetGet('secure_url');
 *     }
 *
 * Isang LIVE na Admin API call kada larawan, kada render. Ang libreng
 * plano ng Cloudinary ay 500 Admin API operations kada oras — kaya ang
 * isang page na may gallery ng villa ay kumakain ng pito o walo sa
 * bawat pag-load, at ilang dosenang pag-browse lang ay ubos na ang
 * quota. Pagkaubos, LAHAT ng larawan sa buong site ay nawawala
 * hanggang sa susunod na oras:
 *
 *     Rate Limit Exceeded. Limit of 500 api operations reached.
 *     Try again on 2026-09-08 10:00:00 UTC
 *
 * Hindi naman kailangan ng API call para dito. Ang delivery URL ng
 * Cloudinary ay tuwirang nabubuo mula sa cloud name at public ID:
 *
 *     https://res.cloudinary.com/{cloud}/image/upload/v1/{public_id}
 *
 * Kaya dito na ito binubuo nang lokal — zero API call, zero quota.
 * Ang Admin API ay para sa pag-alam ng METADATA ng isang asset; hindi
 * natin kailangan ang metadata, ang URL lang.
 */
class MediaUrlHelper
{
    private static ?Cloudinary $cloudinary = null;

    private static bool $cloudinaryResolved = false;

    private static ?FinfoMimeTypeDetector $detector = null;

    /**
     * @param  string|null  $default  Ibinabalik kapag walang path o kapag
     *                                hindi mabuo ang URL.
     */
    public static function resolve(?string $path, ?string $default = null): ?string
    {
        if (blank($path)) {
            return $default;
        }

        // May ilang lumang row na naka-imbak na bilang buong URL.
        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        if (config('filesystems.disks.public.driver') !== 'cloudinary') {
            // Sa local disk, tunay ngang string-building lang ang url().
            try {
                return Storage::disk('public')->url($path);
            } catch (\Throwable $e) {
                Log::error('Failed to resolve local media URL', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);

                return $default;
            }
        }

        return static::cloudinaryUrl($path) ?? $default;
    }

    private static function cloudinaryUrl(string $path): ?string
    {
        $cloudinary = static::cloudinary();

        if (! $cloudinary) {
            return null;
        }

        [$publicId, $type] = static::prepareResource($path);

        try {
            return match ($type) {
                'video' => (string) $cloudinary->video($publicId)->toUrl(),
                'raw' => (string) $cloudinary->raw($publicId)->toUrl(),
                default => (string) $cloudinary->image($publicId)->toUrl(),
            };
        } catch (\Throwable $e) {
            Log::error('Failed to build Cloudinary delivery URL', [
                'path' => $path,
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function cloudinary(): ?Cloudinary
    {
        if (static::$cloudinaryResolved) {
            return static::$cloudinary;
        }

        static::$cloudinaryResolved = true;

        // SADYANG hindi ito kumukuha ng app(Cloudinary::class). Ang
        // singleton ng package ay nagbabasa ng
        // `filesystems.disks.cloudinary` — pero `public` ang pangalan ng
        // disk dito, kaya null ang nakukuha niyon at nagtatayo ito ng
        // Cloudinary na walang cloud_name.
        $url = config('filesystems.disks.public.url');

        if (blank($url)) {
            Log::error('The public disk uses the cloudinary driver but CLOUDINARY_URL is empty — image URLs cannot be built.');

            return null;
        }

        try {
            $cloudinary = new Cloudinary($url);
            // Ang SDK ay nagdaragdag ng `?_a=...` na analytics token sa
            // bawat URL. Walang naidudulot ito sa atin kundi ingay sa
            // markup at sa cache key ng browser.
            $cloudinary->configuration->url->analytics(false);

            return static::$cloudinary = $cloudinary;
        } catch (\Throwable $e) {
            Log::error('Could not build a Cloudinary client from CLOUDINARY_URL', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Sinasalamin nito nang eksakto ang
     * `CloudinaryStorageAdapter::prepareResource()`, dahil IYON ang
     * gumawa ng public ID noong na-upload ang file — kung hindi
     * magkatugma ang dalawa, 404 ang bawat larawan.
     *
     * Dalawang detalye ang madaling makaligtaan:
     *  - Inaalis ang extension sa public ID (`avatars/abc.jpg` →
     *    `avatars/abc`). Para sa image/video, ibinabalik natin ito sa
     *    dulo ng URL bilang delivery format — iyon din ang anyo ng
     *    `secure_url` na dating ibinabalik ng Admin API. Para sa `raw`,
     *    hindi, dahil walang extension ang naka-imbak na public ID.
     *  - Ang mime type ay hinuhulaan mula sa PATH (hindi sa laman ng
     *    file), gamit ang parehong detector ng adapter.
     *
     * @return array{0: string, 1: string}
     */
    private static function prepareResource(string $path): array
    {
        $info = pathinfo($path);
        $dirname = str_replace('\\', '/', $info['dirname'] ?? '.');
        $id = $dirname.'/'.($info['filename'] ?? '');

        $mimeType = (string) static::detector()->detectMimeTypeFromPath($path);
        $extension = $info['extension'] ?? null;

        if (str_starts_with($mimeType, 'image/')) {
            return [$extension ? $id.'.'.$extension : $id, 'image'];
        }

        if (str_starts_with($mimeType, 'video/')) {
            return [$extension ? $id.'.'.$extension : $id, 'video'];
        }

        return [$id, 'raw'];
    }

    private static function detector(): FinfoMimeTypeDetector
    {
        return static::$detector ??= new FinfoMimeTypeDetector;
    }
}
