<?php

namespace App\Http\Controllers;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class ClassStudentController extends Controller
{
    public function destroy(Request $request, string $studentId): JsonResponse
    {
        $validated = $request->validate([
            'classId' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,128}$/'],
        ]);

        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $studentId)) {
            return response()->json(['message' => 'Invalid student ID.'], 422);
        }

        $idToken = $request->bearerToken();

        if (!$idToken) {
            return response()->json(['message' => 'Please sign in again.'], 401);
        }

        try {
            $verifiedToken = Firebase::auth()->verifyIdToken($idToken);
            $teacherUid = $verifiedToken->claims()->get('sub');
        } catch (Throwable $e) {
            return response()->json(['message' => 'Invalid or expired session.'], 401);
        }

        $classId = $validated['classId'];
        $projectId = config('services.firebase.project_id');
        $base = "projects/{$projectId}/databases/(default)/documents";
        $classPath = "{$base}/classes/{$classId}";
        $studentPath = "{$classPath}/students/{$studentId}";

        try {
            // Check class ownership using the signed-in teacher's token.
            $classResponse = Http::withToken($idToken)
                ->timeout(15)
                ->get("https://firestore.googleapis.com/v1/{$classPath}");

            if ($classResponse->status() === 404) {
                return response()->json(['message' => 'Class not found.'], 404);
            }

            if (!$classResponse->successful()) {
                return response()->json(['message' => 'Could not verify class ownership.'], 503);
            }

            if ($classResponse->json('fields.teacherId.stringValue') !== $teacherUid) {
                return response()->json(['message' => 'Only the class teacher can remove students.'], 403);
            }

            // Obtain server-side Firestore credentials.
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

            // Confirm membership exists.
            $studentResponse = Http::withToken($accessToken)
                ->timeout(15)
                ->get("https://firestore.googleapis.com/v1/{$studentPath}");

            if ($studentResponse->status() === 404) {
                return response()->json(['message' => 'Student is not enrolled in this class.'], 404);
            }

            if (!$studentResponse->successful()) {
                return response()->json(['message' => 'Could not verify student membership.'], 503);
            }

            $studentUpdateTime = $studentResponse->json('updateTime');

            // Atomically delete membership and decrement the counter.
            $response = Http::withToken($accessToken)
                ->timeout(20)
                ->post("https://firestore.googleapis.com/v1/{$base}:commit", [
                    'writes' => [
                        [
                            'delete' => $studentPath,
                            'currentDocument' => [
                                'updateTime' => $studentUpdateTime,
                            ],
                        ],
                        [
                            'transform' => [
                                'document' => $classPath,
                                'fieldTransforms' => [
                                    [
                                        'fieldPath' => 'studentCount',
                                        'increment' => [
                                            'integerValue' => '-1',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('Student removal rejected', [
                    'classId' => $classId,
                    'studentId' => $studentId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'message' => 'Could not remove student. Refresh and retry.',
                ], 503);
            }

            return response()->json([
                'success' => true,
                'message' => 'Student removed successfully.',
            ]);
        } catch (Throwable $e) {
            Log::error('Student removal failed', [
                'classId' => $classId,
                'studentId' => $studentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Could not confirm student removal. Refresh before retrying.',
            ], 503);
        }
    }
}
