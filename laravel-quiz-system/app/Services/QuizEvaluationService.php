<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Question;

class QuizEvaluationService
{
    public function evaluate(Question $question, mixed $rawAnswer): array
    {
        return match ($question->type) {
            QuestionType::BINARY, QuestionType::SINGLE_CHOICE => $this->evaluateSingleOption($question, $rawAnswer),
            QuestionType::MULTIPLE_CHOICE => $this->evaluateMultipleOptions($question, $rawAnswer),
            QuestionType::NUMBER => $this->evaluateNumber($question, $rawAnswer),
            QuestionType::TEXT => $this->evaluateText($question, $rawAnswer),
            default => [
                'is_correct' => false,
                'awarded_marks' => 0,
                'option_id' => null,
                'answer_text' => null,
                'answer_number' => null,
                'metadata' => ['reason' => 'unsupported_type'],
            ],
        };
    }

    private function evaluateSingleOption(Question $question, mixed $rawAnswer): array
    {
        $selectedOptionId = is_numeric($rawAnswer) ? (int) $rawAnswer : null;
        $correctOption = $question->options->firstWhere('is_correct', true);
        $isCorrect = $correctOption !== null && $selectedOptionId === $correctOption->id;

        return [
            'is_correct' => $isCorrect,
            'awarded_marks' => $isCorrect ? $question->marks : 0,
            'option_id' => $selectedOptionId,
            'answer_text' => null,
            'answer_number' => null,
            'metadata' => null,
        ];
    }

    private function evaluateMultipleOptions(Question $question, mixed $rawAnswer): array
    {
        $selected = collect(is_array($rawAnswer) ? $rawAnswer : [])
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->sort()
            ->values();

        $correct = $question->options
            ->where('is_correct', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        $isCorrect = $selected->isNotEmpty() && $selected->all() === $correct->all();

        return [
            'is_correct' => $isCorrect,
            'awarded_marks' => $isCorrect ? $question->marks : 0,
            'option_id' => null,
            'answer_text' => null,
            'answer_number' => null,
            'metadata' => ['selected_option_ids' => $selected->all()],
        ];
    }

    private function evaluateNumber(Question $question, mixed $rawAnswer): array
    {
        $expected = $question->settings['correct_number'] ?? null;
        $tolerance = (float) ($question->settings['tolerance'] ?? 0);
        $submitted = is_numeric($rawAnswer) ? (float) $rawAnswer : null;
        $isCorrect = $submitted !== null
            && $expected !== null
            && abs((float) $expected - $submitted) <= $tolerance;

        return [
            'is_correct' => $isCorrect,
            'awarded_marks' => $isCorrect ? $question->marks : 0,
            'option_id' => null,
            'answer_text' => null,
            'answer_number' => $submitted,
            'metadata' => ['expected' => $expected, 'tolerance' => $tolerance],
        ];
    }

    private function evaluateText(Question $question, mixed $rawAnswer): array
    {
        $expected = trim((string) ($question->settings['correct_text'] ?? ''));
        $submitted = trim((string) ($rawAnswer ?? ''));
        $isCorrect = $expected !== '' && strcasecmp($expected, $submitted) === 0;

        return [
            'is_correct' => $isCorrect,
            'awarded_marks' => $isCorrect ? $question->marks : 0,
            'option_id' => null,
            'answer_text' => $submitted === '' ? null : $submitted,
            'answer_number' => null,
            'metadata' => ['expected' => $expected],
        ];
    }
}

