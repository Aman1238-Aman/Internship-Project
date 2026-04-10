# Dynamic Quiz System (Laravel Assignment)

This project is a complete Laravel-based implementation of the **Dynamic Quiz System** assignment.
It supports all required question types, media support, quiz attempts, and automatic scoring.

## Implemented Requirements

- Framework: Laravel (latest stable used during setup)
- Database: SQLite (easy local setup), MySQL-compatible schema design
- Frontend: Blade templates + simple JavaScript
- Storage: Local storage for uploaded images
- Authentication: Not required and not used

### Core Features

- Quiz creation with title and description
- Question types:
  - Binary (Yes/No)
  - Single Choice
  - Multiple Choice
  - Number Input
  - Text Input
- Question editor:
  - Rich text / HTML prompt
  - Question image upload
  - Video URL (YouTube embed supported)
- Options handling:
  - Option can contain text, image, or both
- Quiz attempt:
  - Attempt full quiz from UI
  - Submit answers for all question types
- Evaluation:
  - Marks per question (default 1)
  - Total score calculation
  - Result screen after submission

## Extensibility (Constraint Handling)

Question-type behavior is centralized through:

- `app/Enums/QuestionType.php`
- `app/Support/QuestionTypeCatalog.php`
- `app/Services/QuizEvaluationService.php`

This keeps type logic in one place and avoids hardcoding scattered checks.

## Project Structure Highlights

- `app/Http/Controllers/QuizController.php`
- `app/Http/Controllers/QuestionController.php`
- `app/Http/Controllers/AttemptController.php`
- `app/Models/Quiz.php`
- `app/Models/Question.php`
- `app/Models/Option.php`
- `app/Models/Attempt.php`
- `app/Models/Answer.php`
- `resources/views/...` (all UI screens)

## Setup Instructions

### 1. Install dependencies

```bash
composer install
```

### 2. Environment and key

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Create SQLite DB file

```bash
mkdir -p database
# Windows PowerShell:
if (!(Test-Path database\\database.sqlite)) { New-Item database\\database.sqlite -ItemType File }
```

### 4. Run migrations and seed demo quiz

```bash
php artisan migrate:fresh --seed
```

### 5. Link storage for image preview

```bash
php artisan storage:link
```

### 6. Start server

```bash
php artisan serve
```

Open: `http://127.0.0.1:8000`

## Deliverables Included

- Working Laravel application
- `README.md` (setup and run instructions)
- `ARCHITECTURE.md` (design and extensibility)
- `AI_USAGE.md` (AI usage and corrections)

## Timeline Estimation (Required by Assignment)

Estimated timeline: **8 to 10 hours**

- 1.5 hours: data model + migrations
- 2.5 hours: controllers, question editor, upload flow
- 2 hours: attempt + evaluation engine
- 1.5 hours: UI polish and responsive layout
- 0.5 to 1.5 hours: docs, testing, and final cleanup

### Why this estimate

The main complexity is handling all question types in a clean extensible architecture while keeping upload + evaluation logic stable.

### Work Plan Summary

1. Data model and schema first
2. Type catalog + evaluation service
3. Quiz/question management UI
4. Attempt and result screens
5. Seed data and documentation
6. Final verification for deployment readiness
