<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GameActivityAiService
{
    public function generate(
        string $gameType,
        string $topic
    ): array {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException(
                'Gemini API key is not configured.'
            );
        }

        if (! is_string($model) || $model === '') {
            throw new RuntimeException(
                'Gemini model is not configured.'
            );
        }

        $gameInstructions = match ($gameType) {
            'mind_rush' =>
            'Generate multiple-choice questions. Each item must have question, choices (exactly four strings), and correctIndex (integer from 0 to 3).',

            'link_up' =>
            'Generate matching pairs. Each item must have term and definition.',

            'word_forge' =>
            'Generate fill-in-the-blank questions. Each item must have statement containing [BLANK], answers (array of strings), and choices (array of strings).',

            'letter_quest' =>
            'Generate short-answer questions. Each item must have question and answer.',

            default => throw new RuntimeException(
                'Unsupported game type.'
            ),
        };

        $prompt = <<<PROMPT
You are generating educational game questions for StudyQuest.

Topic: {$topic}

{$gameInstructions}

Requirements:
- Generate exactly 10 items.
- Use accurate educational information.
- Keep questions clear and concise.
- Avoid duplicate questions.
- Use a college-level difficulty appropriate to the topic.
- Return a JSON object with one property named "items".
- Do not include Markdown formatting.
PROMPT;

        $response = Http::timeout(60)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
            ])
            ->post(
                'https://generativelanguage.googleapis.com/v1beta/models/'
                    . $model . ':generateContent',
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );

        if (! $response->successful()) {
            throw new RuntimeException(
                'Gemini generation failed with HTTP '
                    . $response->status()
            );
        }

        $text = $response->json(
            'candidates.0.content.parts.0.text'
        );

        if (! is_string($text) || $text === '') {
            throw new RuntimeException(
                'Gemini returned an empty response.'
            );
        }

        $data = json_decode($text, true);

        if (
            json_last_error() !== JSON_ERROR_NONE ||
            ! is_array($data) ||
            ! isset($data['items']) ||
            ! is_array($data['items']) ||
            count($data['items']) !== 10
        ) {
            throw new RuntimeException(
                'Gemini returned an invalid question structure.'
            );
        }

        return app(GameActivityQuestionValidator::class)
            ->validate($gameType, $data);
    }
}
