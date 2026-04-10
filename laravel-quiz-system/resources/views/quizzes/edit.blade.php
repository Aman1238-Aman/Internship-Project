@extends('layouts.app', ['title' => 'Edit Quiz'])

@section('content')
    <div class="card">
        <h1>Edit Quiz</h1>
        <form method="POST" action="{{ route('quizzes.update', $quiz) }}" class="grid">
            @csrf
            @method('PUT')
            <div>
                <label for="title">Quiz Title</label>
                <input id="title" type="text" name="title" value="{{ old('title', $quiz->title) }}" required>
            </div>
            <div>
                <label for="description">Description</label>
                <textarea id="description" name="description">{{ old('description', $quiz->description) }}</textarea>
            </div>
            <div class="row">
                <button class="btn btn-primary" type="submit">Update Quiz</button>
                <a class="btn btn-secondary" href="{{ route('quizzes.show', $quiz) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection

