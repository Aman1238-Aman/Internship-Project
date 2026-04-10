@extends('layouts.app', ['title' => 'Attempt Result'])

@php use App\Enums\QuestionType; @endphp

@section('content')
    <div class="card">
        <h1>Result: {{ $attempt->quiz->title }}</h1>
        <p class="muted">
            Candidate: <strong>{{ $attempt->participant_name ?: 'Anonymous' }}</strong> |
            Submitted: {{ $attempt->submitted_at?->format('d M Y h:i A') }}
        </p>
        <h2>Score: {{ $attempt->score }} / {{ $attempt->max_score }}</h2>
        <a class="btn btn-primary" href="{{ route('quizzes.show', $attempt->quiz) }}">Back to Quiz</a>
    </div>

    <div class="grid" style="margin-top: 16px;">
        @foreach($attempt->answers as $index => $answer)
            @php $question = $answer->question; @endphp
            <div class="card">
                <div class="row" style="justify-content: space-between;">
                    <h3 style="margin:0;">Q{{ $index + 1 }}</h3>
                    <span class="chip">{{ $answer->awarded_marks }} / {{ $question->marks }}</span>
                </div>
                <div style="margin-top:8px;">{!! $question->question_html !!}</div>

                <p>
                    Status:
                    @if($answer->is_correct)
                        <strong style="color:#0b875b;">Correct</strong>
                    @else
                        <strong style="color:#b42318;">Incorrect</strong>
                    @endif
                </p>

                @if(in_array($question->type, [QuestionType::BINARY, QuestionType::SINGLE_CHOICE], true))
                    <p>Your answer: {{ $answer->option?->text ?? 'Not answered' }}</p>
                @elseif($question->type === QuestionType::MULTIPLE_CHOICE)
                    @php
                        $selectedIds = $answer->metadata['selected_option_ids'] ?? [];
                        $selectedText = $question->options->whereIn('id', $selectedIds)->pluck('text')->filter()->values()->all();
                    @endphp
                    <p>Your answer: {{ $selectedText ? implode(', ', $selectedText) : 'Not answered' }}</p>
                @elseif($question->type === QuestionType::NUMBER)
                    <p>Your answer: {{ $answer->answer_number ?? 'Not answered' }}</p>
                @elseif($question->type === QuestionType::TEXT)
                    <p>Your answer: {{ $answer->answer_text ?? 'Not answered' }}</p>
                @endif
            </div>
        @endforeach
    </div>
@endsection

