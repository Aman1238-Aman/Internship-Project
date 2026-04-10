<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Quiz;
use App\Services\QuizEvaluationService;
use App\Support\QuestionTypeCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttemptController extends Controller
{
    public function create(Quiz $quiz)
    {
        $quiz->load('questions.options');

        return view('attempts.create', compact('quiz'));
    }

    public function store(Request $request, Quiz $quiz, QuizEvaluationService $evaluationService)
    {
        $quiz->load('questions.options');

        $validated = $request->validate([
            'participant_name' => ['nullable', 'string', 'max:255'],
            'answers' => ['required', 'array'],
        ]);

        $result = DB::transaction(function () use ($validated, $quiz, $evaluationService) {
            $attempt = Attempt::create([
                'quiz_id' => $quiz->id,
                'participant_name' => $validated['participant_name'] ?? null,
                'score' => 0,
                'max_score' => $quiz->questions->sum('marks'),
                'submitted_at' => now(),
            ]);

            $score = 0;
            foreach ($quiz->questions as $question) {
                $rawAnswer = $validated['answers'][$question->id] ?? null;
                $evaluation = $evaluationService->evaluate($question, $rawAnswer);
                $score += $evaluation['awarded_marks'];

                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'option_id' => $evaluation['option_id'],
                    'answer_text' => $evaluation['answer_text'],
                    'answer_number' => $evaluation['answer_number'],
                    'is_correct' => $evaluation['is_correct'],
                    'awarded_marks' => $evaluation['awarded_marks'],
                    'metadata' => $evaluation['metadata'],
                ]);
            }

            $attempt->update(['score' => $score]);

            return $attempt->load(['quiz', 'answers.question.options', 'answers.option']);
        });

        return redirect()->route('attempts.show', $result);
    }

    public function show(Attempt $attempt)
    {
        $attempt->load(['quiz', 'answers.question.options', 'answers.option']);
        $typeDefinitions = QuestionTypeCatalog::definitions();

        return view('attempts.show', compact('attempt', 'typeDefinitions'));
    }
}
