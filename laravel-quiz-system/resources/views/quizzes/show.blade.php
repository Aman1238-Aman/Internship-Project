@extends('layouts.app', ['title' => $quiz->title])

@php use App\Support\QuestionTypeCatalog; @endphp

@section('content')
    <div class="card">
        <div class="row" style="justify-content: space-between;">
            <div>
                <h1 style="margin-bottom:8px;">{{ $quiz->title }}</h1>
                <p class="muted">{{ $quiz->description ?: 'No description added.' }}</p>
            </div>
            <div class="row">
                <a class="btn btn-secondary" href="{{ route('quizzes.edit', $quiz) }}">Edit Quiz</a>
                <a class="btn btn-primary" href="{{ route('quizzes.questions.create', $quiz) }}">Add Question</a>
                <a class="btn btn-success" href="{{ route('quizzes.attempt.create', $quiz) }}">Attempt Quiz</a>
                <form method="POST" action="{{ route('quizzes.destroy', $quiz) }}" onsubmit="return confirm('Delete this quiz?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">Delete Quiz</button>
                </form>
            </div>
        </div>
    </div>

    <div class="grid" style="margin-top: 16px;">
        @forelse($quiz->questions as $question)
            <div class="card question-preview">
                <div class="row" style="justify-content: space-between;">
                    <span class="chip">{{ QuestionTypeCatalog::label($question->type->value) }}</span>
                    <span class="chip">{{ $question->marks }} mark(s)</span>
                </div>
                <div style="margin-top: 10px;">{!! $question->question_html !!}</div>

                @if($question->imageUrl())
                    <div style="margin-top: 10px;">
                        <img src="{{ $question->imageUrl() }}" alt="Question image">
                    </div>
                @endif

                @if($question->youtubeEmbedUrl())
                    <div style="margin-top: 12px;">
                        <iframe src="{{ $question->youtubeEmbedUrl() }}" allowfullscreen></iframe>
                    </div>
                @endif

                @if($question->options->isNotEmpty())
                    <ul>
                        @foreach($question->options as $option)
                            <li>
                                {{ $option->text }}
                                @if($option->imageUrl())
                                    <div><img class="option-media" src="{{ $option->imageUrl() }}" alt="Option image"></div>
                                @endif
                                @if($option->is_correct)
                                    <strong>(Correct)</strong>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @elseif($question->type->value === 'number')
                    <p class="muted">Expected number: {{ $question->settings['correct_number'] ?? '-' }}</p>
                @elseif($question->type->value === 'text')
                    <p class="muted">Expected text: {{ $question->settings['correct_text'] ?? '-' }}</p>
                @endif

                <div class="row">
                    <a class="btn btn-secondary" href="{{ route('quizzes.questions.edit', [$quiz, $question]) }}">Edit</a>
                    <form method="POST" action="{{ route('quizzes.questions.destroy', [$quiz, $question]) }}" onsubmit="return confirm('Delete this question?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger" type="submit">Delete</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="card">
                <h3>No questions yet</h3>
                <p class="muted">Add all 5 required types to maximize your assignment score.</p>
            </div>
        @endforelse
    </div>

    @if($quiz->attempts->isNotEmpty())
        <div class="card" style="margin-top: 16px;">
            <h2>Recent Attempts</h2>
            <ul>
                @foreach($quiz->attempts as $attempt)
                    <li>
                        <a href="{{ route('attempts.show', $attempt) }}">
                            {{ $attempt->participant_name ?: 'Anonymous' }}
                            - {{ $attempt->score }}/{{ $attempt->max_score }}
                            ({{ $attempt->submitted_at?->format('d M Y h:i A') }})
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection

