@extends('layouts.app', ['title' => $question->exists ? 'Edit Question' : 'Add Question'])

@php
    $editing = $question->exists;
    $selectedType = old('type', $question->type?->value ?? 'single_choice');
    $settings = $question->settings ?? [];
    $oldOptionTexts = old('option_texts');
    $options = $question->options ?? collect();
@endphp

@section('content')
    <div class="card">
        <h1>{{ $editing ? 'Edit Question' : 'Add Question' }}</h1>
        <p class="muted">Quiz: {{ $quiz->title }}</p>

        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid" id="questionForm">
            @csrf
            @if($method === 'PUT')
                @method('PUT')
            @endif

            <div>
                <label for="type">Question Type</label>
                <select id="type" name="type" required>
                    @foreach($typeDefinitions as $value => $def)
                        <option value="{{ $value }}" @selected($selectedType === $value)>{{ $def['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="question_html">Question Text (HTML allowed)</label>
                <textarea id="question_html" name="question_html" required>{{ old('question_html', $question->question_html) }}</textarea>
            </div>

            <div class="grid grid-2">
                <div>
                    <label for="marks">Marks</label>
                    <input id="marks" type="number" name="marks" min="1" value="{{ old('marks', $question->marks ?? 1) }}" required>
                </div>
                <div>
                    <label for="media_video_url">Video URL (YouTube etc.)</label>
                    <input id="media_video_url" type="url" name="media_video_url" value="{{ old('media_video_url', $question->media_video_url) }}">
                </div>
            </div>

            <div>
                <label for="media_image">Question Image</label>
                <input id="media_image" type="file" name="media_image" accept="image/*">
                @if($question->imageUrl())
                    <div class="row" style="margin-top:8px;">
                        <img class="option-media" src="{{ $question->imageUrl() }}" alt="Current question image">
                        <label style="margin:0;"><input type="checkbox" name="remove_media_image" value="1"> Remove image</label>
                    </div>
                @endif
            </div>

            <div id="binaryFields" style="display:none;">
                <label>Correct Binary Answer</label>
                <label><input type="radio" name="correct_binary" value="yes" @checked(old('correct_binary', ($options->firstWhere('text', 'Yes')?->is_correct ? 'yes' : 'no')) === 'yes')> Yes</label>
                <label><input type="radio" name="correct_binary" value="no" @checked(old('correct_binary', ($options->firstWhere('text', 'No')?->is_correct ? 'no' : 'yes')) === 'no')> No</label>
            </div>

            <div id="numberFields" class="grid grid-2" style="display:none;">
                <div>
                    <label for="correct_number">Correct Number</label>
                    <input id="correct_number" type="number" step="any" name="correct_number" value="{{ old('correct_number', $settings['correct_number'] ?? '') }}">
                </div>
                <div>
                    <label for="number_tolerance">Tolerance (optional)</label>
                    <input id="number_tolerance" type="number" step="any" min="0" name="number_tolerance" value="{{ old('number_tolerance', $settings['tolerance'] ?? 0) }}">
                </div>
            </div>

            <div id="textFields" style="display:none;">
                <label for="correct_text">Correct Text</label>
                <input id="correct_text" type="text" name="correct_text" value="{{ old('correct_text', $settings['correct_text'] ?? '') }}">
            </div>

            <div id="optionsBlock" style="display:none;">
                <div class="row" style="justify-content: space-between;">
                    <label style="margin:0;">Options (Text, Image, or both)</label>
                    <button type="button" class="btn btn-secondary" id="addOptionBtn">Add Option</button>
                </div>
                <div id="optionsWrap" class="grid" style="margin-top:10px;"></div>
            </div>

            <div class="row">
                <button type="submit" class="btn btn-primary">{{ $editing ? 'Update Question' : 'Save Question' }}</button>
                <a class="btn btn-secondary" href="{{ route('quizzes.show', $quiz) }}">Back</a>
            </div>
        </form>
    </div>

    <script>
        const typeSelect = document.getElementById('type');
        const optionsWrap = document.getElementById('optionsWrap');
        const addOptionBtn = document.getElementById('addOptionBtn');
        const optionsBlock = document.getElementById('optionsBlock');
        const binaryFields = document.getElementById('binaryFields');
        const numberFields = document.getElementById('numberFields');
        const textFields = document.getElementById('textFields');

        const existingOptions = @json(old('option_texts') !== null
            ? collect(old('option_texts'))->map(function ($text, $index) {
                return [
                    'text' => $text,
                    'is_correct' => old('single_correct') !== null
                        ? (int) old('single_correct') === $index
                        : array_key_exists($index, old('option_correct', [])),
                    'existing_image' => old('option_existing_image.' . $index),
                ];
            })->values()
            : $options->map(fn($opt) => [
                'text' => $opt->text,
                'is_correct' => $opt->is_correct,
                'existing_image' => $opt->image_path,
                'image_url' => $opt->imageUrl(),
            ])->values()
        );

        function renderTypeSections() {
            const type = typeSelect.value;
            const usesOptions = ['binary', 'single_choice', 'multiple_choice'].includes(type);
            optionsBlock.style.display = usesOptions && type !== 'binary' ? 'block' : 'none';
            binaryFields.style.display = type === 'binary' ? 'block' : 'none';
            numberFields.style.display = type === 'number' ? 'grid' : 'none';
            textFields.style.display = type === 'text' ? 'block' : 'none';
        }

        function optionTemplate(index, option = {}, type = 'single_choice') {
            const checked = option.is_correct ? 'checked' : '';
            const choiceInput = type === 'multiple_choice'
                ? `<input type="checkbox" name="option_correct[${index}]" value="1" ${checked}>`
                : `<input type="radio" name="single_correct" value="${index}" ${checked}>`;
            const preservedImage = option.existing_image
                ? `<input type="hidden" name="option_existing_image[${index}]" value="${option.existing_image}">`
                : '';
            const preview = option.image_url
                ? `<div><img class="option-media" src="${option.image_url}" alt="Option image"></div>`
                : '';

            return `
                <div class="card">
                    <div class="row" style="justify-content: space-between;">
                        <strong>Option ${index + 1}</strong>
                        <button class="btn btn-danger" type="button" onclick="removeOption(this)">Remove</button>
                    </div>
                    <label>Option Text</label>
                    <input type="text" name="option_texts[${index}]" value="${(option.text || '').replace(/"/g, '&quot;')}">
                    <label style="margin-top:8px;">Option Image</label>
                    <input type="file" name="option_images[${index}]" accept="image/*">
                    ${preservedImage}
                    ${preview}
                    <label style="margin-top:8px;">Correct?</label>
                    ${choiceInput}
                </div>`;
        }

        window.removeOption = function (button) {
            button.closest('.card').remove();
        }

        function addOption(option = {}) {
            const type = typeSelect.value;
            const index = optionsWrap.children.length;
            optionsWrap.insertAdjacentHTML('beforeend', optionTemplate(index, option, type));
        }

        addOptionBtn.addEventListener('click', () => addOption());

        typeSelect.addEventListener('change', () => {
            renderTypeSections();
            if (!['single_choice', 'multiple_choice'].includes(typeSelect.value)) {
                optionsWrap.innerHTML = '';
            }
        });

        renderTypeSections();
        if (['single_choice', 'multiple_choice'].includes(typeSelect.value)) {
            if (existingOptions.length > 0) {
                existingOptions.forEach(option => addOption(option));
            } else {
                addOption();
                addOption();
            }
        }
    </script>
@endsection
