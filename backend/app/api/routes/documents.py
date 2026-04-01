import asyncio
import uuid

from fastapi import APIRouter, Depends, File, HTTPException, Query, UploadFile, status
from fastapi.responses import Response
from sse_starlette import EventSourceResponse
from sqlalchemy.orm import Session

from app.core.db import get_db
from app.models.document import Document, DocumentStatus
from app.schemas.document import ApiMessage, DocumentListResponse, DocumentRead, FinalizeRequest, ReviewUpdateRequest
from app.services.document_service import (
    build_document_query,
    count_documents,
    export_document,
    get_document_or_404,
    save_upload_file,
)
from app.services.progress import publish_progress, store_progress_event, subscribe_to_progress
from app.worker.tasks import process_document


router = APIRouter(prefix="/documents", tags=["documents"])


@router.post("", response_model=list[DocumentRead])
def upload_documents(files: list[UploadFile] = File(...), db: Session = Depends(get_db)) -> list[Document]:
    uploaded_documents: list[Document] = []
    for upload in files:
        storage_path, size = save_upload_file(upload)
        document = Document(
            filename=storage_path.split("/")[-1].split("\\")[-1],
            original_name=upload.filename or "untitled",
            content_type=upload.content_type or "application/octet-stream",
            size_bytes=size,
            storage_path=storage_path,
            status=DocumentStatus.queued,
            latest_event="job_queued",
            progress_percent=0,
        )
        db.add(document)
        db.flush()

        store_progress_event(
            db,
            document,
            event_type="job_queued",
            message="Document uploaded and queued for processing.",
            progress_percent=0,
            status=DocumentStatus.queued,
        )
        db.commit()
        db.refresh(document)
        asyncio.run(
            publish_progress(
                document.id,
                {
                    "event_type": "job_queued",
                    "message": "Document uploaded and queued for processing.",
                    "progress_percent": 0,
                    "status": document.status.value,
                },
            )
        )

        async_result = process_document.delay(str(document.id))
        document.celery_task_id = async_result.id
        db.commit()
        db.refresh(document)
        uploaded_documents.append(document)
    return uploaded_documents


@router.get("", response_model=DocumentListResponse)
def list_documents(
    search: str | None = Query(default=None),
    status: DocumentStatus | None = Query(default=None),
    sort: str = Query(default="newest"),
    db: Session = Depends(get_db),
) -> DocumentListResponse:
    query = build_document_query(search, status, sort)
    items = db.execute(query).scalars().all()
    total = count_documents(db, search, status)
    return DocumentListResponse(items=items, total=total)


@router.get("/{document_id}", response_model=DocumentRead)
def get_document(document_id: uuid.UUID, db: Session = Depends(get_db)) -> Document:
    return get_document_or_404(db, document_id)


@router.put("/{document_id}/review", response_model=DocumentRead)
def update_review(document_id: uuid.UUID, payload: ReviewUpdateRequest, db: Session = Depends(get_db)) -> Document:
    document = get_document_or_404(db, document_id)
    for field, value in payload.model_dump(exclude_unset=True).items():
        setattr(document, field, value)
    document.final_output = {
        "title": document.title,
        "category": document.category,
        "summary": document.summary,
        "extracted_keywords": document.extracted_keywords,
        "review_notes": document.review_notes,
    }
    db.commit()
    db.refresh(document)
    return document


@router.post("/{document_id}/retry", response_model=ApiMessage)
def retry_document(document_id: uuid.UUID, db: Session = Depends(get_db)) -> ApiMessage:
    document = get_document_or_404(db, document_id)
    if document.status != DocumentStatus.failed:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Document is not in failed state")

    document.retry_count += 1
    document.status = DocumentStatus.queued
    document.progress_percent = 0
    document.error_message = None
    db.commit()

    store_progress_event(
        db,
        document,
        event_type="job_queued",
        message="Document re-queued for retry.",
        progress_percent=0,
        status=DocumentStatus.queued,
    )
    db.commit()
    asyncio.run(
        publish_progress(
            document.id,
            {
                "event_type": "job_queued",
                "message": "Document re-queued for retry.",
                "progress_percent": 0,
                "status": document.status.value,
            },
        )
    )

    process_document.delay(str(document.id))
    return ApiMessage(message="Retry started")


@router.post("/{document_id}/finalize", response_model=DocumentRead)
def finalize_document(document_id: uuid.UUID, payload: FinalizeRequest, db: Session = Depends(get_db)) -> Document:
    document = get_document_or_404(db, document_id)
    if document.status not in {DocumentStatus.completed, DocumentStatus.finalized}:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Only completed documents can be finalized",
        )

    document.status = DocumentStatus.finalized
    document.review_notes = (
        f"{document.review_notes or ''}\nFinalized by {payload.approver_name}. {payload.final_notes or ''}".strip()
    )
    document.final_output = {
        **(document.final_output or {}),
        "approved_by": payload.approver_name,
        "final_notes": payload.final_notes,
        "finalized": True,
    }
    store_progress_event(
        db,
        document,
        event_type="job_finalized",
        message="Result finalized and ready for export.",
        progress_percent=100,
        status=DocumentStatus.finalized,
    )
    db.commit()
    db.refresh(document)
    return document


@router.get("/{document_id}/export")
def export_result(document_id: uuid.UUID, format: str = Query(default="json"), db: Session = Depends(get_db)) -> Response:
    document = get_document_or_404(db, document_id)
    content, media_type, filename = export_document(document, format)
    headers = {"Content-Disposition": f'attachment; filename="{filename}"'}
    return Response(content=content, media_type=media_type, headers=headers)


@router.get("/{document_id}/events")
async def stream_progress(document_id: uuid.UUID):
    async def event_generator():
        async for message in subscribe_to_progress(document_id):
            yield {"event": "progress", "data": message}

    return EventSourceResponse(event_generator())
