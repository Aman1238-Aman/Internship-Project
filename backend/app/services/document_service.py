import csv
import io
import json
import uuid
from pathlib import Path

from fastapi import HTTPException, UploadFile, status
from sqlalchemy import Select, asc, desc, func, or_, select
from sqlalchemy.orm import Session, selectinload

from app.core.config import get_settings
from app.models.document import Document, DocumentStatus


settings = get_settings()


def build_document_query(search: str | None, status_filter: DocumentStatus | None, sort: str) -> Select[tuple[Document]]:
    query = select(Document).options(selectinload(Document.events))
    if search:
        pattern = f"%{search.strip()}%"
        query = query.where(
            or_(Document.original_name.ilike(pattern), Document.title.ilike(pattern), Document.summary.ilike(pattern))
        )
    if status_filter:
        query = query.where(Document.status == status_filter)

    sort_column = {
        "name": asc(Document.original_name),
        "progress": desc(Document.progress_percent),
        "oldest": asc(Document.created_at),
    }.get(sort, desc(Document.created_at))

    return query.order_by(sort_column)


def save_upload_file(upload: UploadFile) -> tuple[str, int]:
    suffix = Path(upload.filename or "").suffix
    stored_name = f"{uuid.uuid4()}{suffix}"
    destination = settings.upload_dir / stored_name
    contents = upload.file.read()
    destination.write_bytes(contents)
    return str(destination), len(contents)


def get_document_or_404(db: Session, document_id: uuid.UUID) -> Document:
    document = db.execute(
        select(Document).options(selectinload(Document.events)).where(Document.id == document_id)
    ).scalar_one_or_none()
    if not document:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Document not found")
    return document


def export_document(document: Document, export_format: str) -> tuple[bytes, str, str]:
    payload = {
        "id": str(document.id),
        "title": document.title,
        "category": document.category,
        "summary": document.summary,
        "keywords": document.extracted_keywords or [],
        "review_notes": document.review_notes,
        "status": document.status.value,
        "original_name": document.original_name,
        "created_at": document.created_at.isoformat(),
    }

    if export_format == "json":
        return json.dumps(payload, indent=2).encode("utf-8"), "application/json", f"{document.id}.json"

    if export_format == "csv":
        stream = io.StringIO()
        writer = csv.DictWriter(stream, fieldnames=list(payload.keys()))
        writer.writeheader()
        writer.writerow({**payload, "keywords": ", ".join(payload["keywords"])})
        return stream.getvalue().encode("utf-8"), "text/csv", f"{document.id}.csv"

    raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail="Unsupported export format")


def count_documents(db: Session, search: str | None, status_filter: DocumentStatus | None) -> int:
    query = select(func.count(Document.id))
    if search:
        pattern = f"%{search.strip()}%"
        query = query.where(
            or_(Document.original_name.ilike(pattern), Document.title.ilike(pattern), Document.summary.ilike(pattern))
        )
    if status_filter:
        query = query.where(Document.status == status_filter)
    return db.execute(query).scalar_one()
