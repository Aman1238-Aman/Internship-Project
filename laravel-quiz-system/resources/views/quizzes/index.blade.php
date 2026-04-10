@extends('layouts.app', ['title' => 'All Quizzes'])

@section('content')
    <div class="card">
        <h1>Quiz Dashboard</h1>
        <p class="muted">Create quizzes, define mixed question types, and evaluate attempts instantly.</p>
    </div>

    <div class="grid grid-2" style="margin-top: 16px;">
        @forelse($quizzes as $quiz)
            <div class="card">
                <div class="row" style="justify-content: space-between;">
                    <h2 style="margin:0;">{{ $quiz->title }}</h2>
                    <span class="chip">{{ $quiz->questions_count }} questions</span>
                </div>
                <p class="muted">{{ $quiz->description ?: 'No description yet.' }}</p>
                <div class="row">
                    <a class="btn btn-primary" href="{{ route('quizzes.show', $quiz) }}">Manage</a>
                    <a class="btn btn-success" href="{{ route('quizzes.attempt.create', $quiz) }}">Attempt</a>
                </div>
            </div>
        @empty
            <div class="card">
                <h2>No quizzes yet</h2>
                <p class="muted">Start by creating your first quiz for this assignment.</p>
                <a class="btn btn-primary" href="{{ route('quizzes.create') }}">Create Quiz</a>
            </div>
        @endforelse
    </div>
@endsection

