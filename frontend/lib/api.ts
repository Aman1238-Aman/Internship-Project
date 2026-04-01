import { DocumentListResponse, DocumentRecord, DocumentStatus } from "@/lib/types";

const API_BASE = process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:8000/api";

async function parseResponse<T>(response: Response): Promise<T> {
  if (!response.ok) {
    const errorText = await response.text();
    throw new Error(errorText || "Request failed");
  }
  return response.json() as Promise<T>;
}

async function safeFetch(input: RequestInfo | URL, init?: RequestInit): Promise<Response> {
  try {
    return await fetch(input, init);
  } catch (error) {
    if (error instanceof TypeError) {
      throw new Error(
        "Backend unreachable. Please start Docker Desktop and run `docker compose up --build`, then refresh localhost:3000."
      );
    }
    throw error;
  }
}

export async function fetchDocuments(filters: {
  search?: string;
  status?: DocumentStatus | "all";
  sort?: string;
}): Promise<DocumentListResponse> {
  const params = new URLSearchParams();
  if (filters.search) params.set("search", filters.search);
  if (filters.status && filters.status !== "all") params.set("status", filters.status);
  if (filters.sort) params.set("sort", filters.sort);

  const response = await safeFetch(`${API_BASE}/documents?${params.toString()}`, {
    cache: "no-store"
  });
  return parseResponse<DocumentListResponse>(response);
}

export async function fetchDocument(id: string): Promise<DocumentRecord> {
  const response = await safeFetch(`${API_BASE}/documents/${id}`, { cache: "no-store" });
  return parseResponse<DocumentRecord>(response);
}

export async function uploadDocuments(files: File[]): Promise<DocumentRecord[]> {
  const formData = new FormData();
  files.forEach((file) => formData.append("files", file));

  const response = await safeFetch(`${API_BASE}/documents`, {
    method: "POST",
    body: formData
  });
  return parseResponse<DocumentRecord[]>(response);
}

export async function updateReview(id: string, payload: Partial<DocumentRecord>): Promise<DocumentRecord> {
  const response = await safeFetch(`${API_BASE}/documents/${id}/review`, {
    method: "PUT",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      title: payload.title,
      category: payload.category,
      summary: payload.summary,
      extracted_keywords: payload.extracted_keywords,
      review_notes: payload.review_notes
    })
  });
  return parseResponse<DocumentRecord>(response);
}

export async function finalizeDocument(id: string, approverName: string, finalNotes: string): Promise<DocumentRecord> {
  const response = await safeFetch(`${API_BASE}/documents/${id}/finalize`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      approver_name: approverName,
      final_notes: finalNotes
    })
  });
  return parseResponse<DocumentRecord>(response);
}

export async function retryDocument(id: string): Promise<void> {
  const response = await safeFetch(`${API_BASE}/documents/${id}/retry`, {
    method: "POST"
  });
  if (!response.ok) {
    throw new Error("Retry failed");
  }
}

export function exportUrl(id: string, format: "json" | "csv") {
  return `${API_BASE}/documents/${id}/export?format=${format}`;
}

export function eventsUrl(id: string) {
  return `${API_BASE}/documents/${id}/events`;
}
