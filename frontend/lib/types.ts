export type DocumentStatus = "queued" | "processing" | "completed" | "failed" | "finalized";

export interface DocumentEvent {
  id: number;
  event_type: string;
  message: string;
  progress_percent: number;
  payload: Record<string, unknown> | null;
  created_at: string;
}

export interface DocumentRecord {
  id: string;
  filename: string;
  original_name: string;
  content_type: string;
  size_bytes: number;
  status: DocumentStatus;
  category: string | null;
  title: string | null;
  summary: string | null;
  extracted_keywords: string[] | null;
  parsed_text: string | null;
  review_notes: string | null;
  final_output: Record<string, unknown> | null;
  latest_event: string | null;
  progress_percent: number;
  retry_count: number;
  error_message: string | null;
  created_at: string;
  updated_at: string | null;
  events: DocumentEvent[];
}

export interface DocumentListResponse {
  items: DocumentRecord[];
  total: number;
}
