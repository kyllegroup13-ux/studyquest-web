<?php

namespace App\Http\Controllers;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProfileImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => [
                'required',
                'image',
                'max:5120',
                'mimes:jpg,jpeg,png,webp',
            ],
        ]);

        $idToken = $request->bearerToken();
        if (! $idToken) {
            return response()->json(['message' => 'Please sign in again.'], 401);
        }

        $uid = $this->verifyFirebaseToken($idToken);
        if (! $uid) {
            return response()->json(['message' => 'Your session is invalid or expired. Please sign in again.'], 401);
        }

        try {
            $result = Cloudinary::uploadApi()->upload(
                $validated['image']->getRealPath(),
                [
                    'folder' => 'duolarp/profiles/'.$uid,
                    'resource_type' => 'image',
                ]
            );
        } catch (Throwable $e) {
            Log::error('Cloudinary profile image upload failed', [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Could not upload your profile image. Please try again.'], 502);
        }

        $profileImageUrl = $result['secure_url'] ?? null;
        if (! is_string($profileImageUrl) || ! str_starts_with($profileImageUrl, 'https://')) {
            Log::error('Cloudinary returned an invalid profile image URL', [
                'uid' => $uid,
            ]);

            return response()->json(['message' => 'Could not verify the uploaded image. Please try again.'], 502);
        }

        return response()->json([
            'profileImageUrl' => $profileImageUrl,
        ], 201);
    }

    private function verifyFirebaseToken(string $idToken): ?string
    {
        $response = Http::timeout(10)->post(
            'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='.config('services.firebase.api_key'),
            ['idToken' => $idToken]
        );

        if (! $response->successful()) {
            return null;
        }

        return $response->json('users.0.localId');
    }
}
