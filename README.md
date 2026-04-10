# DocFlow Studio

DocFlow Studio is a production-style full stack assignment submission for an async document processing workflow system. It uses a TypeScript Next.js frontend, a FastAPI backend, PostgreSQL persistence, Celery workers, and Redis Pub/Sub powered live progress updates.

## What this submission covers

- Upload one or more documents
- Store metadata and job details in PostgreSQL
- Trigger real background processing through Celery
- Publish progress events through Redis Pub/Sub
- Show queued, processing, completed, failed, and finalized states
- Review and edit extracted structured output
- Retry failed jobs
- Finalize reviewed output
- Export finalized records as JSON and CSV
- Search, filter, and sort documents from the dashboard
- Attractive responsive UI for upload, dashboard, review, and export flow

## Tech stack

- Frontend: Next.js 15 + TypeScript
- Backend: FastAPI + SQLAlchemy
- Database: PostgreSQL
- Background jobs: Celery
- Broker / PubSub: Redis
- Container orchestration: Docker Compose

## Project structure

```text
backend/
  app/
    api/routes/documents.py
    core/
    models/
    schemas/
    services/
    worker/
frontend/
  app/
  components/
  lib/
sample-data/
  documents/
  exports/
docker-compose.yml
README.md
```

## Architecture overview

1. User uploads one or more files from the Next.js dashboard.
2. FastAPI stores the file on disk and saves document metadata in PostgreSQL.
3. FastAPI creates an initial `job_queued` event and dispatches a Celery task.
4. Celery worker processes the file in multiple stages:
   - `job_started`
   - `document_parsing_started`
   - `document_parsing_completed`
   - `field_extraction_started`
   - `field_extraction_completed`
   - `job_completed` or `job_failed`
5. Each stage is persisted in the database and published to Redis Pub/Sub.
6. FastAPI exposes an SSE endpoint that streams Redis Pub/Sub progress messages to the frontend.
7. Frontend updates the dashboard in near real time and allows review, finalize, retry, and export actions.

## API surface

- `POST /api/documents`
- `GET /api/documents`
- `GET /api/documents/{document_id}`
- `PUT /api/documents/{document_id}/review`
- `POST /api/documents/{document_id}/retry`
- `POST /api/documents/{document_id}/finalize`
- `GET /api/documents/{document_id}/export?format=json`
- `GET /api/documents/{document_id}/export?format=csv`
- `GET /api/documents/{document_id}/events`

## Processing logic

The processing logic is intentionally simple because the assignment evaluates async system design and engineering quality more than OCR quality.

- Extract metadata such as filename, content type, and size
- Read plain text files directly
- Mock parse non-text files
- Generate structured fields:
  - title
  - category
  - summary
  - extracted keywords
- Persist final structured JSON to PostgreSQL

## Setup instructions

### Option 1: Docker Compose

```bash
docker compose up --build
```

Services:

- Frontend: `http://localhost:3000`
- Backend API: `http://localhost:8000`
- PostgreSQL: `localhost:5432`
- Redis: `localhost:6379`

### Option 2: Manual local run

Backend:

```bash
cd backend
python -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload
```

Worker:

```bash
cd backend
celery -A app.worker.celery_app.celery_app worker --loglevel=info
```

Frontend:

```bash
cd frontend
npm install
npm run dev
```

Environment values can be copied from [backend/.env.example](/C:/Users/Lenovo/OneDrive/Desktop/cursor%20new/Documents/Playground/backend/.env.example).

## Render deployment notes

If frontend and backend are deployed as separate Render services, set these values:

Frontend service environment:

- `NEXT_PUBLIC_API_BASE_URL=https://<your-backend-service>.onrender.com/api`

Backend service environment:

- `FRONTEND_ORIGIN=https://<your-frontend-service>.onrender.com`
- `FRONTEND_ORIGINS=https://<your-frontend-service>.onrender.com,http://localhost:3000,http://127.0.0.1:3000`

If frontend and backend are served from the same domain behind a reverse proxy, the frontend will now default to `/<api>` on the current origin when `NEXT_PUBLIC_API_BASE_URL` is not set.

## Assumptions

- File storage is local disk for assignment simplicity.
- Database tables are auto-created at app startup instead of using full migrations.
- Server-Sent Events are used for progress visibility.
- Parsing is mocked for non-text files.
- Authentication is intentionally excluded because it is listed as a bonus item.

## Tradeoffs

- Local file storage is simpler than S3-style abstraction but faster to review in an internship assignment.
- Auto `create_all()` keeps setup short, though Alembic would be preferred in production.
- SSE is simpler than WebSocket for one-way progress updates.
- Mock parsing keeps focus on architecture and background workflow correctness.

## Limitations

- No auth layer
- No cancel job endpoint yet
- No formal test suite added yet
- No chunked upload or large-file optimization yet
- No production-grade file scanner or OCR pipeline

## Submission checklist mapping

- README with setup, architecture, assumptions, tradeoffs, limitations: included
- Sample files for testing: included under `sample-data/documents`
- Sample exported outputs: included under `sample-data/exports`
- Async background processing: implemented with Celery
- Redis Pub/Sub progress tracking: implemented
- Dashboard + detail/review + finalize + export: implemented

## Demo guidance

For the required 3 to 5 minute video, show this flow:

1. Start Docker Compose.
2. Open the dashboard.
3. Upload 2 sample files from `sample-data/documents`.
4. Show live status changing from queued to processing to completed.
5. Open one document, edit review fields, finalize it, then export JSON and CSV.
6. Optionally simulate a failed job and retry it.

## AI usage note

AI assistance was used to accelerate scaffolding, UI composition, and implementation drafting. All code and architecture were reviewed and organized into a coherent async workflow submission.
