<?php

use App\Http\Controllers\AttemptController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuizController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('quizzes.index'));

Route::resource('quizzes', QuizController::class);

Route::get('/quizzes/{quiz}/questions/create', [QuestionController::class, 'create'])->name('quizzes.questions.create');
Route::post('/quizzes/{quiz}/questions', [QuestionController::class, 'store'])->name('quizzes.questions.store');
Route::get('/quizzes/{quiz}/questions/{question}/edit', [QuestionController::class, 'edit'])->name('quizzes.questions.edit');
Route::put('/quizzes/{quiz}/questions/{question}', [QuestionController::class, 'update'])->name('quizzes.questions.update');
Route::delete('/quizzes/{quiz}/questions/{question}', [QuestionController::class, 'destroy'])->name('quizzes.questions.destroy');

Route::get('/quizzes/{quiz}/attempt', [AttemptController::class, 'create'])->name('quizzes.attempt.create');
Route::post('/quizzes/{quiz}/attempt', [AttemptController::class, 'store'])->name('quizzes.attempt.store');
Route::get('/attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
