<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Quiz;
use App\Support\QuestionTypeCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    public function create(Quiz $quiz)
    {
        $typeDefinitions = QuestionTypeCatalog::definitions();

        return view('questions.form', [
            'quiz' => $quiz,
            'question' => new Question(['marks' => 1]),
            'typeDefinitions' => $typeDefinitions,
            'action' => route('quizzes.questions.store', $quiz),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request, Quiz $quiz)
    {
        $validated = $this->validateQuestion($request);

        DB::transaction(function () use ($request, $quiz, $validated): void {
            $question = $quiz->questions()->create([
                'type' => $validated['type'],
                'question_html' => $validated['question_html'],
                'media_image_path' => $request->hasFile('media_image')
                    ? $request->file('media_image')->store('question-images', 'public')
                    : null,
                'media_video_url' => $validated['media_video_url'] ?? null,
                'marks' => $validated['marks'],
                'display_order' => ($quiz->questions()->max('display_order') ?? 0) + 1,
                'settings' => $this->buildSettings($validated),
            ]);

            $this->syncOptions($question, $validated, $request);
        });

        return redirect()
            ->route('quizzes.show', $quiz)
            ->with('success', 'Question added successfully.');
    }

    public function edit(Quiz $quiz, Question $question)
    {
        abort_unless($question->quiz_id === $quiz->id, 404);
        $question->load('options');
        $typeDefinitions = QuestionTypeCatalog::definitions();

        return view('questions.form', [
            'quiz' => $quiz,
            'question' => $question,
            'typeDefinitions' => $typeDefinitions,
            'action' => route('quizzes.questions.update', [$quiz, $question]),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, Quiz $quiz, Question $question)
    {
        abort_unless($question->quiz_id === $quiz->id, 404);
        $validated = $this->validateQuestion($request);

        DB::transaction(function () use ($request, $question, $validated): void {
            $imagePath = $question->media_image_path;

            if ($request->boolean('remove_media_image') && $imagePath) {
                Storage::disk('public')->delete($imagePath);
                $imagePath = null;
            }

            if ($request->hasFile('media_image')) {
                if ($imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }
                $imagePath = $request->file('media_image')->store('question-images', 'public');
            }

            $question->update([
                'type' => $validated['type'],
                'question_html' => $validated['question_html'],
                'media_image_path' => $imagePath,
                'media_video_url' => $validated['media_video_url'] ?? null,
                'marks' => $validated['marks'],
                'settings' => $this->buildSettings($validated),
            ]);

            $this->syncOptions($question, $validated, $request, true);
        });

        return redirect()
            ->route('quizzes.show', $quiz)
            ->with('success', 'Question updated successfully.');
    }

    public function destroy(Quiz $quiz, Question $question)
    {
        abort_unless($question->quiz_id === $quiz->id, 404);

        if ($question->media_image_path) {
            Storage::disk('public')->delete($question->media_image_path);
        }

        foreach ($question->options as $option) {
            if ($option->image_path) {
                Storage::disk('public')->delete($option->image_path);
            }
        }

        $question->delete();

        return redirect()
            ->route('quizzes.show', $quiz)
            ->with('success', 'Question deleted.');
    }

    private function validateQuestion(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(QuestionTypeCatalog::values())],
            'question_html' => ['required', 'string'],
            'marks' => ['required', 'integer', 'min:1'],
            'media_image' => ['nullable', 'image', 'max:4096'],
            'media_video_url' => ['nullable', 'url'],
            'option_texts' => ['array'],
            'option_texts.*' => ['nullable', 'string'],
            'option_correct' => ['array'],
            'option_correct.*' => ['nullable'],
            'single_correct' => ['nullable', 'integer', 'min:0'],
            'option_existing_image' => ['array'],
            'correct_binary' => ['nullable', Rule::in(['yes', 'no'])],
            'correct_number' => ['nullable', 'numeric'],
            'number_tolerance' => ['nullable', 'numeric', 'min:0'],
            'correct_text' => ['nullable', 'string'],
            'remove_media_image' => ['nullable', 'boolean'],
        ]);

        $type = $validated['type'];
        if ($type === 'number' && ! isset($validated['correct_number'])) {
            throw ValidationException::withMessages([
                'correct_number' => 'Correct number is required for number input questions.',
            ]);
        }
        if ($type === 'text' && trim((string) ($validated['correct_text'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'correct_text' => 'Correct text is required for text input questions.',
            ]);
        }

        if (QuestionTypeCatalog::usesOptions($type)) {
            $optionTexts = collect($validated['option_texts'] ?? []);
            $optionFiles = collect($request->file('option_images', []));
            $optionCount = max($optionTexts->count(), $optionFiles->count());

            if ($type === 'binary') {
                return $validated;
            }

            if ($optionCount < 2) {
                throw ValidationException::withMessages([
                    'option_texts' => 'Please provide at least 2 options.',
                ]);
            }
        }

        return $validated;
    }

    private function buildSettings(array $validated): array
    {
        return match ($validated['type']) {
            'number' => [
                'correct_number' => isset($validated['correct_number']) ? (float) $validated['correct_number'] : null,
                'tolerance' => isset($validated['number_tolerance']) ? (float) $validated['number_tolerance'] : 0.0,
            ],
            'text' => [
                'correct_text' => trim((string) ($validated['correct_text'] ?? '')),
            ],
            default => [],
        };
    }

    private function syncOptions(Question $question, array $validated, Request $request, bool $isUpdate = false): void
    {
        if (! QuestionTypeCatalog::usesOptions($validated['type'])) {
            foreach ($question->options as $option) {
                if ($option->image_path) {
                    Storage::disk('public')->delete($option->image_path);
                }
            }
            $question->options()->delete();
            return;
        }

        if ($validated['type'] === 'binary') {
            foreach ($question->options as $option) {
                if ($option->image_path) {
                    Storage::disk('public')->delete($option->image_path);
                }
            }
            $correctBinary = $validated['correct_binary'] ?? 'yes';
            $question->options()->delete();
            $question->options()->createMany([
                ['text' => 'Yes', 'is_correct' => $correctBinary === 'yes', 'display_order' => 1],
                ['text' => 'No', 'is_correct' => $correctBinary === 'no', 'display_order' => 2],
            ]);

            return;
        }

        $texts = $validated['option_texts'] ?? [];
        $files = $request->file('option_images', []);
        $existingImages = $validated['option_existing_image'] ?? [];
        $correctIndexes = [];
        if (($validated['type'] ?? null) === 'multiple_choice') {
            $correctIndexes = collect(array_keys($validated['option_correct'] ?? []))
                ->map(fn ($index) => (int) $index)
                ->all();
        } else {
            $singleCorrect = $validated['single_correct'] ?? null;
            if ($singleCorrect !== null) {
                $correctIndexes = [(int) $singleCorrect];
            }
        }

        if ($isUpdate) {
            foreach ($question->options as $option) {
                if ($option->image_path) {
                    Storage::disk('public')->delete($option->image_path);
                }
            }
            $question->options()->delete();
        }

        $rows = [];
        $count = max(count($texts), count($files), count($existingImages));

        for ($index = 0; $index < $count; $index++) {
            $text = trim((string) ($texts[$index] ?? ''));
            $imagePath = $existingImages[$index] ?? null;
            if (isset($files[$index]) && $files[$index] !== null) {
                $imagePath = $files[$index]->store('option-images', 'public');
            }

            if ($text === '' && ! $imagePath) {
                continue;
            }

            $rows[] = [
                'text' => $text === '' ? null : $text,
                'image_path' => $imagePath ?: null,
                'is_correct' => in_array($index, $correctIndexes, true),
                'display_order' => $index + 1,
            ];
        }

        if (count($rows) < 2) {
            throw ValidationException::withMessages([
                'option_texts' => 'Please provide at least 2 non-empty options.',
            ]);
        }

        if (QuestionTypeCatalog::singleChoice($validated['type'])) {
            $correctCount = collect($rows)->where('is_correct', true)->count();
            if ($correctCount !== 1) {
                throw ValidationException::withMessages([
                    'single_correct' => 'Single choice questions need exactly one correct option.',
                ]);
            }
        } else {
            $correctCount = collect($rows)->where('is_correct', true)->count();
            if ($correctCount < 1) {
                throw ValidationException::withMessages([
                    'option_correct' => 'Multiple choice questions need at least one correct option.',
                ]);
            }
        }

        $question->options()->createMany($rows);
    }
}
