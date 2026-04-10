# Architecture Notes

## 1. Objective

The system is designed to support multiple quiz question types, media-enabled content, and automatic evaluation while staying extensible.

## 2. Data Model

Core tables:

- `quizzes`: quiz metadata
- `questions`: question prompt, type, marks, media, settings
- `options`: selectable options for option-based types
- `attempts`: quiz submission container with total score
- `answers`: per-question evaluated answers

### Why this model works

- Supports all current types
- Keeps attempt history and result reproducibility
- Allows future question types without changing attempt schema

## 3. Type System Design

Type logic is centralized:

- `QuestionType` enum defines supported types
- `QuestionTypeCatalog` defines per-type behavior metadata
- `QuizEvaluationService` contains evaluation rules by type

This avoids hardcoded conditional logic spread across many files.

## 4. Evaluation Flow

1. User submits quiz attempt
2. `AttemptController` loops through quiz questions
3. Each answer is evaluated by `QuizEvaluationService`
4. Result details are persisted in `answers`
5. Final total is stored in `attempts.score`

Evaluation output always follows a normalized shape:

- `is_correct`
- `awarded_marks`
- captured answer fields (`option_id`, `answer_text`, `answer_number`)
- metadata when needed (e.g. multiple selected IDs)

## 5. Media Handling

- Question image and option image uploads are stored in local `public` disk
- Public URLs are resolved via `Storage::disk('public')->url(...)`
- YouTube links are transformed to embed URL when possible

## 6. UI Design Decisions

- Blade templates keep the stack simple and assignment-compliant
- Minimal JavaScript is used only where dynamic option rows are needed
- Responsive card-based layout improves clarity for evaluators
- Rich visual style added while preserving readability and performance

## 7. Extending with New Question Types

To add a new type:

1. Add a new enum case in `QuestionType`
2. Add metadata in `QuestionTypeCatalog`
3. Add evaluation branch in `QuizEvaluationService`
4. Add form + attempt rendering in Blade templates

No schema redesign is required for most future types.

## 8. Tradeoffs

- Chosen SQLite by default for easy reviewer setup
- Text-answer matching is case-insensitive exact match (simple and deterministic)
- Number-answer checking supports tolerance via `settings`

## 9. Deployment Readiness

- Standard Laravel structure
- `.env.example` configured for no-auth local run
- Works with `php artisan migrate --seed` and `php artisan serve`
- Storage symlink command included for media rendering
