import json
from collections.abc import AsyncIterator
from uuid import UUID

from redis import asyncio as aioredis
from sqlalchemy.orm import Session

from app.core.config import get_settings
from app.models.document import Document, DocumentEvent, DocumentStatus


settings = get_settings()


def progress_channel(document_id: UUID) -> str:
    return f"document-progress:{document_id}"


async def publish_progress(document_id: UUID, event: dict) -> None:
    client = aioredis.from_url(settings.redis_url, decode_responses=True)
    try:
        await client.publish(progress_channel(document_id), json.dumps(event))
    finally:
        await client.aclose()


def store_progress_event(
    db: Session,
    document: Document,
    *,
    event_type: str,
    message: str,
    progress_percent: int,
    status: DocumentStatus | None = None,
    payload: dict | None = None,
) -> DocumentEvent:
    if status is not None:
        document.status = status
    document.progress_percent = progress_percent
    document.latest_event = event_type
    event = DocumentEvent(
        document=document,
        event_type=event_type,
        message=message,
        progress_percent=progress_percent,
        payload=payload,
    )
    db.add(event)
    db.flush()
    return event


async def subscribe_to_progress(document_id: UUID) -> AsyncIterator[str]:
    client = aioredis.from_url(settings.redis_url, decode_responses=True)
    pubsub = client.pubsub()
    await pubsub.subscribe(progress_channel(document_id))
    try:
        async for message in pubsub.listen():
            if message.get("type") == "message":
                yield message["data"]
    finally:
        await pubsub.unsubscribe(progress_channel(document_id))
        await pubsub.aclose()
        await client.aclose()
