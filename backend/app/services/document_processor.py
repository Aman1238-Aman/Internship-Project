from pathlib import Path

from pypdf import PdfReader


def _extract_pdf_text(file_path: Path) -> str:
    reader = PdfReader(str(file_path))
    pages: list[str] = []
    for page in reader.pages:
        pages.append(page.extract_text() or "")
    return "\n".join(part.strip() for part in pages if part.strip())


def _extract_key_value_fields(parsed_text: str) -> dict[str, str]:
    details: dict[str, str] = {}
    for line in parsed_text.splitlines():
        stripped = line.strip()
        if ":" not in stripped:
            continue
        key, value = stripped.split(":", 1)
        normalized_key = key.strip().lower().replace(" ", "_")
        normalized_value = value.strip()
        if normalized_key and normalized_value:
            details[normalized_key] = normalized_value
    return details


def parse_document_contents(file_path: Path, content_type: str) -> str:
    suffix = file_path.suffix.lower()

    if content_type.startswith("text/") or suffix in {".txt", ".md", ".csv"}:
        try:
            return file_path.read_text(encoding="utf-8", errors="ignore")
        except OSError:
            return "Unable to read text file."

    if content_type == "application/pdf" or suffix == ".pdf":
        try:
            extracted = _extract_pdf_text(file_path)
            if extracted.strip():
                return extracted
            return "PDF text extraction completed, but no readable text was found in the file."
        except Exception:
            return "PDF parsing failed. The file may be image-based or unsupported."

    size = file_path.stat().st_size if file_path.exists() else 0
    return (
        f"Limited parser output for {file_path.name}. "
        f"Detected content type: {content_type}. File size: {size} bytes."
    )


def extract_structured_fields(filename: str, parsed_text: str) -> dict:
    cleaned = " ".join(parsed_text.split())
    summary = cleaned[:320] + ("..." if len(cleaned) > 320 else "")
    words = [word.strip(".,:;!?()[]{}").lower() for word in cleaned.split()]
    keywords = [word for word in words if len(word) > 4 and not word.isnumeric()]
    unique_keywords = list(dict.fromkeys(keywords))[:10]
    fallback_title = Path(filename).stem.replace("-", " ").replace("_", " ").title()

    lowered = cleaned.lower()
    category = "General Report"
    if any(token in lowered for token in ["invoice", "payment", "amount", "vendor"]):
        category = "Finance"
    elif any(token in lowered for token in ["resume", "experience", "education", "candidate"]):
        category = "HR"
    elif any(token in lowered for token in ["employee", "manager", "department", "performance review"]):
        category = "Employee Review"
    elif any(token in lowered for token in ["contract", "agreement", "party"]):
        category = "Legal"

    details = _extract_key_value_fields(parsed_text)
    detected_title = (
        details.get("title")
        or details.get("report_title")
        or details.get("employee_name")
        or details.get("candidate_name")
        or next((line.strip() for line in parsed_text.splitlines() if line.strip()), fallback_title)
    )

    structured = {
        "title": detected_title or fallback_title or "Untitled Document",
        "category": category,
        "summary": summary or "No summary available.",
        "extracted_keywords": unique_keywords or ["document", "review", "processing"],
        "details": details,
        "status": "review_ready",
    }

    if category == "Employee Review":
        structured["employee_profile"] = {
            "employee_name": details.get("employee_name"),
            "employee_id": details.get("employee_id"),
            "department": details.get("department"),
            "designation": details.get("designation"),
            "review_period": details.get("review_period"),
            "reporting_manager": details.get("reporting_manager"),
            "final_status": details.get("final_status"),
        }

    return structured
