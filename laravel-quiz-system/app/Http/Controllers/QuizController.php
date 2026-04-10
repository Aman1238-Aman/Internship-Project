<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function index()
    {
        $quizzes = Quiz::withCount('questions')->latest()->get();

        return view('quizzes.index', compact('quizzes'));
    }

    public function create()
    {
        return view('quizzes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $quiz = Quiz::create($validated);

        return redirect()
            ->route('quizzes.show', $quiz)
            ->with('success', 'Quiz created. Add questions now.');
    }

    public function show(Quiz $quiz)
    {
        $quiz->load(['questions.options', 'attempts' => fn ($query) => $query->withCount('answers')->latest()->limit(10)]);

        return view('quizzes.show', compact('quiz'));
    }

    public function edit(Quiz $quiz)
    {
        return view('quizzes.edit', compact('quiz'));
    }

    public function update(Request $request, Quiz $quiz)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $quiz->update($validated);

        return redirect()
            ->route('quizzes.show', $quiz)
            ->with('success', 'Quiz details updated.');
    }

    public function destroy(Quiz $quiz)
    {
        $quiz->delete();

        return redirect()
            ->route('quizzes.index')
            ->with('success', 'Quiz deleted successfully.');
    }
}
