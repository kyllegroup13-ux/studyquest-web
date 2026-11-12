<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GameActivityQuestionValidator
{
    public function validate(string $gameType, array $data): array
    {
        $rules = [
            'items' => ['required', 'array', 'size:10'],
        ];

        switch ($gameType) {
            case 'mind_rush':
                $rules += [
                    'items.*' => [
                        'required', 'array:question,choices,correctIndex',
                    ],
                    'items.*.question' => [
                        'required', 'string', 'max:500',
                    ],
                    'items.*.choices' => [
                        'required', 'array', 'size:4',
                    ],
                    'items.*.choices.*' => [
                        'required', 'string', 'max:200',
                    ],
                    'items.*.correctIndex' => [
                        'required', 'integer', 'between:0,3',
                    ],
                ];
                break;

            case 'link_up':
                $rules += [
                    'items.*' => [
                        'required', 'array:term,definition',
                    ],
                    'items.*.term' => [
                        'required', 'string', 'max:100',
                    ],
                    'items.*.definition' => [
                        'required', 'string', 'max:500',
                    ],
                ];
                break;

            case 'word_forge':
                $rules += [
                    'items.*' => [
                        'required', 'array:statement,answers,choices',
                    ],
                    'items.*.statement' => [
                        'required', 'string', 'max:500',
                    ],
                    'items.*.answers' => [
                        'required', 'array', 'min:1', 'max:10',
                    ],
                    'items.*.answers.*' => [
                        'required', 'string', 'max:100',
                    ],
                    'items.*.choices' => [
                        'required', 'array', 'min:1', 'max:20',
                    ],
                    'items.*.choices.*' => [
                        'required', 'string', 'max:100',
                    ],
                ];
                break;

            case 'letter_quest':
                $rules += [
                    'items.*' => [
                        'required', 'array:question,answer',
                    ],
                    'items.*.question' => [
                        'required', 'string', 'max:500',
                    ],
                    'items.*.answer' => [
                        'required', 'string', 'max:100',
                    ],
                ];
                break;

            default:
                throw new RuntimeException(
                    'Unsupported game type: ' . $gameType
                );
        }

        $validated = Validator::make($data, $rules)->validate();

        $this->validateUniqueness($gameType, $validated['items']);

        if ($gameType === 'word_forge') {
            $this->validateWordForge($validated['items']);
        }

        if ($gameType === 'mind_rush') {
            $this->validateMindRush($validated['items']);
        }

        return $validated;
    }

    private function validateUniqueness(
        string $gameType,
        array $items
    ): void {
        $field = match ($gameType) {
            'link_up' => 'term',
            'word_forge' => 'statement',
            default => 'question',
        };

        $values = array_map(
            fn (array $item) =>
                mb_strtolower(trim($item[$field])),
            $items
        );

        if (count($values) !== count(array_unique($values))) {
            throw ValidationException::withMessages([
                'items' => 'Generated questions contain duplicates.',
            ]);
        }
    }

    private function validateWordForge(array $items): void
    {
        foreach ($items as $index => $item) {
            if (! str_contains($item['statement'], '[BLANK]')) {
                throw ValidationException::withMessages([
                    "items.{$index}.statement" =>
                        'Word Forge statement must contain [BLANK].',
                ]);
            }

            $answers = array_map(
                fn ($value) => mb_strtolower(trim($value)),
                $item['answers']
            );

            $choices = array_map(
                fn ($value) => mb_strtolower(trim($value)),
                $item['choices']
            );

            foreach ($answers as $answer) {
                if (! in_array($answer, $choices, true)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.choices" =>
                            'Word Forge choices must include all answers.',
                    ]);
                }
            }

            if (count($choices) !== count(array_unique($choices))) {
                throw ValidationException::withMessages([
                    "items.{$index}.choices" =>
                        'Word Forge choices must be unique.',
                ]);
            }
        }
    }

    private function validateMindRush(array $items): void
    {
        foreach ($items as $index => $item) {
            $choices = array_map(
                fn ($value) => mb_strtolower(trim($value)),
                $item['choices']
            );

            if (count($choices) !== count(array_unique($choices))) {
                throw ValidationException::withMessages([
                    "items.{$index}.choices" =>
                        'Mind Rush choices must be unique.',
                ]);
            }
        }
    }
}
