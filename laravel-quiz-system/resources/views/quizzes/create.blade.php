@extends('layouts.app', ['title' => 'Create Quiz'])

@section('content')
    <div class="card">
        <h1>Create Quiz</h1>
        <form method="POST" action="{{ route('quizzes.store') }}" class="grid">
            @csrf
            <div>
                <label for="title">Quiz Title</label>
                <input id="title" type="text" name="title" value="{{ old('title') }}" required>
            </div>
            <div>
                <label for="description">Description</label>
                <textarea id="description" name="description">{{ old('description') }}</textarea>
            </div>
            <div class="row">
                <button class="btn btn-primary" type="submit">Save Quiz</button>
                <a class="btn btn-secondary" href="{{ route('quizzes.index') }}">Back</a>
            </div>
        </form>
    </div>
@endsection

