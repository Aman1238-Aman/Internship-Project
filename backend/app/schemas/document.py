from datetime import datetime
from uuid import UUID

from pydantic import BaseModel, Field

from app.models.document import DocumentStatus


class DocumentEventRead(BaseModel):
    id: int
    event_type: str
    message: str
    progress_percent: int
    payload: dict | None
    created_at: datetime

    model_config = {"from_attributes": True}


class DocumentRead(BaseModel):
    id: UUID
    filename: str
    original_name: str
    content_type: str
    size_bytes: int
    status: DocumentStatus
    category: str | None
    title: str | None
    summary: str | None
    extracted_keywords: list[str] | None
    parsed_text: str | None
    review_notes: str | None
    final_output: dict | None
    latest_event: str | None
    progress_percent: int
    retry_count: int
    error_message: str | None
    created_at: datetime
    updated_at: datetime | None
    events: list[DocumentEventRead] = []

    model_config = {"from_attributes": True}


class DocumentListResponse(BaseModel):
    items: list[DocumentRead]
    total: int


class ReviewUpdateRequest(BaseModel):
    title: str | None = None
    category: str | None = None
    summary: str | None = None
    extracted_keywords: list[str] | None = None
    review_notes: str | None = None


class FinalizeRequest(BaseModel):
    approver_name: str = Field(min_length=2, max_length=100)
    final_notes: str | None = None


class ApiMessage(BaseModel):
    message: str
