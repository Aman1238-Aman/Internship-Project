@extends('layouts.app', ['title' => 'Attempt Quiz'])

@php use App\Enums\QuestionType; @endphp

@section('content')
    <div class="card">
        <h1>Attempt: {{ $quiz->title }}</h1>
        <p class="muted">{{ $quiz->description }}</p>

        <form method="POST" action="{{ route('quizzes.attempt.store', $quiz) }}" class="grid">
            @csrf
            <div>
                <label for="participant_name">Your Name (optional)</label>
                <input id="participant_name" type="text" name="participant_name" value="{{ old('participant_name') }}">
            </div>

            @foreach($quiz->questions as $index => $question)
                <div class="card question-preview">
                    <h3>Q{{ $index + 1 }}. {!! $question->question_html !!}</h3>
                    <div class="muted">Marks: {{ $question->marks }}</div>

                    @if($question->imageUrl())
                        <div style="margin-top:8px;"><img src="{{ $question->imageUrl() }}" alt="Question image"></div>
                    @endif
                    @if($question->youtubeEmbedUrl())
                        <div style="margin-top:8px;"><iframe src="{{ $question->youtubeEmbedUrl() }}" allowfullscreen></iframe></div>
                    @endif

                    @if(in_array($question->type, [QuestionType::BINARY, QuestionType::SINGLE_CHOICE], true))
                        @foreach($question->options as $option)
                            <label class="row">
                                <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option->id }}">
                                <span>{{ $option->text }}</span>
                            </label>
                            @if($option->imageUrl())
                                <img class="option-media" src="{{ $option->imageUrl() }}" alt="Option image">
                            @endif
                        @endforeach
                    @elseif($question->type === QuestionType::MULTIPLE_CHOICE)
                        @foreach($question->options as $option)
                            <label class="row">
                                <input type="checkbox" name="answers[{{ $question->id }}][]" value="{{ $option->id }}">
                                <span>{{ $option->text }}</span>
                            </label>
                            @if($option->imageUrl())
                                <img class="option-media" src="{{ $option->imageUrl() }}" alt="Option image">
                            @endif
                        @endforeach
                    @elseif($question->type === QuestionType::NUMBER)
                        <input type="number" name="answers[{{ $question->id }}]" step="any">
                    @elseif($question->type === QuestionType::TEXT)
                        <input type="text" name="answers[{{ $question->id }}]">
                    @endif
                </div>
            @endforeach

            <div class="row">
                <button class="btn btn-primary" type="submit">Submit Quiz</button>
                <a class="btn btn-secondary" href="{{ route('quizzes.show', $quiz) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection

