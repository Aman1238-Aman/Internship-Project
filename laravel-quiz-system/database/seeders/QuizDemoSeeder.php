<?php

namespace Database\Seeders;

use App\Enums\QuestionType;
use App\Models\Quiz;
use Illuminate\Database\Seeder;

class QuizDemoSeeder extends Seeder
{
    public function run(): void
    {
        $quiz = Quiz::firstOrCreate(
            ['title' => 'Laravel Developer Assignment Demo Quiz'],
            ['description' => 'Sample quiz with all required question types, media-ready structure, and automatic evaluation.']
        );

        if ($quiz->questions()->exists()) {
            return;
        }

        $binary = $quiz->questions()->create([
            'type' => QuestionType::BINARY,
            'question_html' => '<p>Laravel is a PHP framework.</p>',
            'marks' => 1,
            'display_order' => 1,
            'settings' => [],
        ]);
        $binary->options()->createMany([
            ['text' => 'Yes', 'is_correct' => true, 'display_order' => 1],
            ['text' => 'No', 'is_correct' => false, 'display_order' => 2],
        ]);

        $single = $quiz->questions()->create([
            'type' => QuestionType::SINGLE_CHOICE,
            'question_html' => '<p>Which command creates a Laravel migration?</p>',
            'marks' => 1,
            'display_order' => 2,
            'settings' => [],
        ]);
        $single->options()->createMany([
            ['text' => 'php artisan make:migration', 'is_correct' => true, 'display_order' => 1],
            ['text' => 'php artisan cache:clear', 'is_correct' => false, 'display_order' => 2],
            ['text' => 'php artisan route:list', 'is_correct' => false, 'display_order' => 3],
        ]);

        $multiple = $quiz->questions()->create([
            'type' => QuestionType::MULTIPLE_CHOICE,
            'question_html' => '<p>Select valid Laravel features:</p>',
            'marks' => 2,
            'display_order' => 3,
            'settings' => [],
        ]);
        $multiple->options()->createMany([
            ['text' => 'Eloquent ORM', 'is_correct' => true, 'display_order' => 1],
            ['text' => 'Blade Templating', 'is_correct' => true, 'display_order' => 2],
            ['text' => 'Direct DOM API only', 'is_correct' => false, 'display_order' => 3],
        ]);

        $quiz->questions()->create([
            'type' => QuestionType::NUMBER,
            'question_html' => '<p>How many default environment files does a fresh Laravel app include? (Enter number)</p>',
            'marks' => 1,
            'display_order' => 4,
            'settings' => ['correct_number' => 2, 'tolerance' => 0],
        ]);

        $quiz->questions()->create([
            'type' => QuestionType::TEXT,
            'question_html' => '<p>Type the templating engine name used by Laravel.</p>',
            'marks' => 1,
            'display_order' => 5,
            'settings' => ['correct_text' => 'blade'],
        ]);
    }
}
