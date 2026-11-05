<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class ClassController extends Controller
{
    public function destroy(Request $request, string $classId): JsonResponse
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $classId)) {
            return response()->json([
                'message' => 'Invalid class ID.',
            ], 422);
        }

        // 1. Verify the signed-in teacher.
        $idToken = $request->bearerToken();

        if (!$idToken) {
            return response()->json([
                'message' => 'Please sign in again.',
            ], 401);
        }

        try {
            $verifiedToken = Firebase::auth()->verifyIdToken($idToken);
            $teacherUid = $verifiedToken->claims()->get('sub');
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Your session is invalid or expired.',
            ], 401);
        }

        $projectId = config('services.firebase.project_id');

        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $firestoreUrl = 'https://firestore.googleapis.com/v1/';

        try {
            // 2. Check that the class belongs to this teacher.
            $classResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get($firestoreUrl . $classPath);

            if ($classResponse->status() === 404) {
                return response()->json([
                    'message' => 'Class not found.',
                ], 404);
            }

            if (!$classResponse->successful()) {
                return response()->json([
                    'message' => 'Could not verify class ownership.',
                ], 503);
            }

            if ($classResponse->json('fields.teacherId.stringValue') !== $teacherUid) {
                return response()->json([
                    'message' => 'Only the class teacher can delete this class.',
                ], 403);
            }

            // 3. Obtain service-account credentials.
            $credentialsPath = config('firebase.projects.app.credentials');

            $resolvedPath = str_starts_with($credentialsPath, '/')
                ? $credentialsPath
                : base_path($credentialsPath);

            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/datastore'],
                $resolvedPath
            );

            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? null;

            if (!$accessToken) {
                throw new \RuntimeException('Missing Firestore access token.');
            }

            // 4. Delete class subcollections before deleting parent.
            foreach (['posts', 'students'] as $subcollection) {
                $this->deleteSubcollection(
                    $accessToken,
                    $firestoreUrl,
                    $classPath,
                    $subcollection
                );
            }

            // 5. Delete the parent class document.
            $deleteResponse = Http::withToken($accessToken)
                ->timeout(20)
                ->delete($firestoreUrl . $classPath);

            if (!$deleteResponse->successful()) {
                Log::error('Class document deletion rejected', [
                    'classId' => $classId,
                    'status' => $deleteResponse->status(),
                    'body' => $deleteResponse->body(),
                ]);

                return response()->json([
                    'message' => 'Could not delete the class document.',
                ], 503);
            }

            return response()->json([
                'success' => true,
                'message' => 'Class deleted successfully.',
            ]);
        } catch (Throwable $e) {
            Log::error('Class deletion failed', [
                'classId' => $classId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not complete class deletion. Refresh before retrying.',
            ], 503);
        }
    }

    private function deleteSubcollection(
        string $accessToken,
        string $firestoreUrl,
        string $classPath,
        string $subcollection
    ): void {
        $collectionUrl = $firestoreUrl . $classPath . '/' . $subcollection;
        $commitUrl = $firestoreUrl . explode('/classes/', $classPath)[0] . ':commit';

        // Process small pages to avoid exceeding Firestore write limits.
        do {
            $listResponse = Http::withToken($accessToken)
                ->timeout(20)
                ->get($collectionUrl, [
                    'pageSize' => 100,
                ]);

            if (!$listResponse->successful()) {
                throw new \RuntimeException(
                    "Could not list {$subcollection} documents."
                );
            }

            $documents = $listResponse->json('documents', []);

            if (empty($documents)) {
                break;
            }

            foreach (array_chunk($documents, 100) as $chunk) {
                $writes = array_map(
                    static fn(array $document): array => [
                        'delete' => $document['name'],
                        'currentDocument' => [
                            'updateTime' => $document['updateTime'],
                        ],
                    ],
                    $chunk
                );

                $response = Http::withToken($accessToken)
                    ->timeout(30)
                    ->post($commitUrl, [
                        'writes' => $writes,
                    ]);

                if (!$response->successful()) {
                    throw new \RuntimeException(
                        "Could not delete {$subcollection} documents."
                    );
                }
            }

            // Always query the first page again because documents
            // are being removed during each iteration.
        } while (true);
    }
}
