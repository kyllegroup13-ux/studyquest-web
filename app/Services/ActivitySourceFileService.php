<?php

namespace App\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ActivitySourceFileService
{
    public function download(array $sourceFile): string
    {
        $publicId = $sourceFile['publicId'] ?? '';
        $resourceType = $sourceFile['resourceType'] ?? '';
        $deliveryType = $sourceFile['deliveryType'] ?? '';
        $bytes = (int) ($sourceFile['bytes'] ?? 0);

        if (
            ! is_string($publicId) ||
            $publicId === '' ||
            $resourceType !== 'raw' ||
            $deliveryType !== 'authenticated'
        ) {
            throw new RuntimeException(
                'Invalid activity source metadata.'
            );
        }

        if ($bytes <= 0 || $bytes > 10 * 1024 * 1024) {
            throw new RuntimeException(
                'Activity source exceeds the allowed size.'
            );
        }

        // Generate a short-lived authenticated download URL.

        $url = Cloudinary::uploadApi()
            ->privateDownloadUrl($publicId, '', [
                'resource_type' => 'raw',
                'type' => 'authenticated',
                'expires_at' => time() + 300,
            ]);


        $response = Http::timeout(30)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Could not retrieve the activity source.'
            );
        }

        $contents = $response->body();

        if (
            $contents === '' ||
            strlen($contents) > 10 * 1024 * 1024
        ) {
            throw new RuntimeException(
                'Downloaded source is empty or too large.'
            );
        }

        return $contents;
    }
}
