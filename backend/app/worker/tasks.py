import asyncio
from pathlib import Path
from uuid import UUID

from celery import shared_task
from sqlalchemy import select

from app.core.db import SessionLocal
from app.models.document import Document, DocumentStatus
from app.services.document_processor import extract_structured_fields, parse_document_contents
from app.services.progress import publish_progress, store_progress_event


def _emit(db, document: Document, *, event_type: str, message: str, progress_percent: int, status=None, payload=None):
    event = store_progress_event(
        db,
        document,
        event_type=event_type,
        message=message,
        progress_percent=progress_percent,
        status=status,
        payload=payload,
    )
    db.commit()
    db.refresh(document)
    asyncio.run(
        publish_progress(
            document.id,
            {
                "id": event.id,
                "event_type": event_type,
                "message": message,
                "progress_percent": progress_percent,
                "status": document.status.value,
                "payload": payload,
            },
        )
    )


@shared_task(bind=True, max_retries=2, default_retry_delay=10)
def process_document(self, document_id: str) -> None:
    db = SessionLocal()
    try:
        document = db.execute(select(Document).where(Document.id == UUID(document_id))).scalar_one()
        document.celery_task_id = self.request.id
        db.commit()
        db.refresh(document)

        _emit(
            db,
            document,
            event_type="job_started",
            message="Background job picked up by Celery worker.",
            progress_percent=10,
            status=DocumentStatus.processing,
        )

        _emit(
            db,
            document,
            event_type="document_parsing_started",
            message="Parsing document contents.",
            progress_percent=25,
        )
        parsed_text = parse_document_contents(Path(document.storage_path), document.content_type)
        document.parsed_text = parsed_text
        db.commit()

        _emit(
            db,
            document,
            event_type="document_parsing_completed",
            message="Parsing finished successfully.",
            progress_percent=45,
            payload={"characters": len(parsed_text)},
        )

        _emit(
            db,
            document,
            event_type="field_extraction_started",
            message="Generating structured fields.",
            progress_percent=60,
        )
        extracted = extract_structured_fields(document.original_name, parsed_text)
        document.title = extracted["title"]
        document.category = extracted["category"]
        document.summary = extracted["summary"]
        document.extracted_keywords = extracted["extracted_keywords"]
        document.review_notes = (
            f"Auto extracted {len(extracted.get('details', {}))} key details from the document."
            if extracted.get("details")
            else "Auto extraction completed."
        )
        document.final_output = extracted
        db.commit()

        _emit(
            db,
            document,
            event_type="field_extraction_completed",
            message="Structured output is ready for review.",
            progress_percent=85,
            payload=extracted,
        )

        _emit(
            db,
            document,
            event_type="job_completed",
            message="Processing completed. Document is ready for review.",
            progress_percent=100,
            status=DocumentStatus.completed,
        )
    except Exception as exc:
        document = db.execute(select(Document).where(Document.id == UUID(document_id))).scalar_one_or_none()
        if document:
            document.error_message = str(exc)
            _emit(
                db,
                document,
                event_type="job_failed",
                message="Processing failed. You can retry this document.",
                progress_percent=document.progress_percent or 0,
                status=DocumentStatus.failed,
                payload={"error": str(exc)},
            )
        raise
    finally:
        db.close()
