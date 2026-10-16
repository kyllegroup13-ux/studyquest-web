<?php

namespace App\Http\Controllers;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassAttachmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // 1. Validate the request and the file (type + size).
        $validated = $request->validate([
            'class_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,128}$/'],
            'file' => [
                'required',
                'file',
                'max:10240', // 10 MB (Cloudinary free plan limit)
                'mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png,gif,webp',
            ],
        ]);

        // 2. Verify the Firebase ID token (never trust a UID sent by the browser).
        $idToken = $request->bearerToken();
        if (! $idToken) {
            return response()->json(['message' => 'Please sign in again.'], 401);
        }

        $uid = $this->verifyFirebaseToken($idToken);
        if (! $uid) {
            return response()->json(['message' => 'Your session is invalid or expired. Please sign in again.'], 401);
        }

        // 3. Confirm this user is the teacher of this class.
        if (! $this->isClassTeacher($validated['class_id'], $uid, $idToken)) {
            return response()->json(['message' => 'Only the class teacher can upload attachments.'], 403);
        }

        // 4. Upload to Cloudinary.
        $file = $request->file('file');

        try {
            $result = Cloudinary::uploadApi()->upload($file->getRealPath(), [
                'folder' => 'duolarp/classes/'.$validated['class_id'],
                'resource_type' => 'auto',
            ]);
        } catch (Throwable $e) {
            Log::error('Cloudinary upload failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not upload the file. Please try again.'], 502);
        }

        return response()->json([
            'fileName' => $file->getClientOriginalName(),
            'fileUrl' => $result['secure_url'],
            'filePublicId' => $result['public_id'],
            'fileResourceType' => $result['resource_type'],
            'fileBytes' => $result['bytes'],
        ], 201);
    }

    /**
     * Ask Firebase Auth to validate the ID token. Returns the user's UID or null.
     */
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

    /**
     * Read the class document as the signed-in user (so Firestore rules apply)
     * and compare its teacherId to the verified UID.
     */
    private function isClassTeacher(string $classId, string $uid, string $idToken): bool
    {
        $projectId = config('services.firebase.project_id');

        $response = Http::withToken($idToken)->timeout(10)->get(
            "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/classes/{$classId}"
        );

        if (! $response->successful()) {
            return false;
        }

        return $response->json('fields.teacherId.stringValue') === $uid;
    }
}
