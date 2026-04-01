"use client";

import { ChangeEvent, useEffect, useMemo, useState, useTransition } from "react";

import {
  eventsUrl,
  exportUrl,
  fetchDocument,
  fetchDocuments,
  finalizeDocument,
  retryDocument,
  updateReview,
  uploadDocuments
} from "@/lib/api";
import { DocumentEvent, DocumentRecord, DocumentStatus } from "@/lib/types";

import styles from "./dashboard.module.css";

const statusTone: Record<DocumentStatus, string> = {
  queued: styles.statusQueued,
  processing: styles.statusProcessing,
  completed: styles.statusCompleted,
  failed: styles.statusFailed,
  finalized: styles.statusFinalized
};

const formatDate = (value: string) =>
  new Intl.DateTimeFormat("en-IN", {
    day: "2-digit",
    month: "short",
    hour: "2-digit",
    minute: "2-digit"
  }).format(new Date(value));

function mergeDocument(existing: DocumentRecord[], next: DocumentRecord) {
  const found = existing.some((item) => item.id === next.id);
  if (!found) {
    return [next, ...existing];
  }

  return existing.map((item) => (item.id === next.id ? next : item));
}

function parseEventData(payload: string): Partial<DocumentRecord> & { events?: DocumentEvent[] } {
  const parsed = JSON.parse(payload) as {
    id: number;
    event_type: string;
    message: string;
    progress_percent: number;
    status: DocumentStatus;
    payload?: Record<string, unknown>;
  };

  return {
    status: parsed.status,
    progress_percent: parsed.progress_percent,
    latest_event: parsed.event_type,
    events: [
      {
        id: parsed.id,
        event_type: parsed.event_type,
        message: parsed.message,
        progress_percent: parsed.progress_percent,
        payload: parsed.payload ?? null,
        created_at: new Date().toISOString()
      }
    ]
  };
}

export function Dashboard() {
  const [documents, setDocuments] = useState<DocumentRecord[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState<DocumentStatus | "all">("all");
  const [sort, setSort] = useState("newest");
  const [approverName, setApproverName] = useState("Hiring Manager");
  const [finalNotes, setFinalNotes] = useState("Approved after structured review.");
  const [reviewDraft, setReviewDraft] = useState({
    title: "",
    category: "",
    summary: "",
    extracted_keywords: "",
    review_notes: ""
  });
  const [isPending, startTransition] = useTransition();
  const [uploading, setUploading] = useState(false);
  const [actionMessage, setActionMessage] = useState("Ready to process documents.");
  const [loadingDocuments, setLoadingDocuments] = useState(false);

  const selectedDocument = useMemo(
    () => documents.find((document) => document.id === selectedId) ?? null,
    [documents, selectedId]
  );
  const activeDocumentIds = useMemo(
    () =>
      documents
        .filter((document) => document.status === "queued" || document.status === "processing")
        .map((document) => document.id),
    [documents]
  );

  useEffect(() => {
    let cancelled = false;

    async function loadDocuments() {
      try {
        if (!cancelled) {
          setLoadingDocuments(true);
        }
        const data = await fetchDocuments({ search, status, sort });
        if (cancelled) {
          return;
        }

        startTransition(() => {
          setDocuments(data.items);
          if (!selectedId && data.items[0]) {
            setSelectedId(data.items[0].id);
          }
        });
      } catch (error) {
        if (!cancelled) {
          setActionMessage(error instanceof Error ? error.message : "Unable to fetch documents.");
        }
      } finally {
        if (!cancelled) {
          setLoadingDocuments(false);
        }
      }
    }

    void loadDocuments();

    return () => {
      cancelled = true;
    };
  }, [search, status, sort, selectedId]);

  useEffect(() => {
    if (!selectedDocument) {
      return;
    }

    setReviewDraft({
      title: selectedDocument.title ?? "",
      category: selectedDocument.category ?? "",
      summary: selectedDocument.summary ?? "",
      extracted_keywords: (selectedDocument.extracted_keywords ?? []).join(", "),
      review_notes: selectedDocument.review_notes ?? ""
    });
  }, [selectedDocument]);

  useEffect(() => {
    const subscriptions = activeDocumentIds.map((documentId) => {
      const stream = new EventSource(eventsUrl(documentId));
      stream.addEventListener("progress", async (event) => {
        const messageEvent = event as MessageEvent<string>;
        const patch = parseEventData(messageEvent.data);
        setDocuments((current) =>
          current.map((item) =>
            item.id === documentId
              ? {
                  ...item,
                  ...patch,
                  events: [...(item.events ?? []), ...((patch.events as DocumentEvent[]) ?? [])]
                }
              : item
          )
        );

        if (patch.status === "completed" || patch.status === "failed") {
          const fresh = await fetchDocument(documentId);
          setDocuments((current) => mergeDocument(current, fresh));
        }
      });
      stream.onerror = () => {
        stream.close();
      };
      return stream;
    });

    return () => {
      subscriptions.forEach((subscription) => subscription.close());
    };
  }, [activeDocumentIds]);

  const totalProcessed = documents.filter((item) => item.status === "completed" || item.status === "finalized").length;
  const totalFailures = documents.filter((item) => item.status === "failed").length;
  const activeJobs = documents.filter((item) => item.status === "queued" || item.status === "processing").length;

  async function refreshDocuments() {
    try {
      setLoadingDocuments(true);
      const data = await fetchDocuments({ search, status, sort });
      setDocuments(data.items);
      if (selectedId) {
        const refreshed = data.items.find((item) => item.id === selectedId);
        if (!refreshed) {
          setSelectedId(data.items[0]?.id ?? null);
        }
      }
      setActionMessage(
        data.items.length
          ? `Dashboard refreshed. ${data.items.length} document(s) available.`
          : "No documents found in dashboard right now."
      );
    } catch (error) {
      setActionMessage(error instanceof Error ? error.message : "Refresh failed.");
    } finally {
      setLoadingDocuments(false);
    }
  }

  async function handleUpload(event: ChangeEvent<HTMLInputElement>) {
    const list = Array.from(event.target.files ?? []);
    if (!list.length) return;

    setUploading(true);
    setActionMessage("Uploading files and queueing Celery jobs...");
    try {
      const created = await uploadDocuments(list);
      setDocuments((current) => [...created, ...current]);
      setSelectedId(created[0]?.id ?? selectedId);
      setActionMessage("Documents uploaded successfully. Live processing has started.");
      await refreshDocuments();
    } catch (error) {
      setActionMessage(error instanceof Error ? error.message : "Upload failed.");
    } finally {
      setUploading(false);
      event.target.value = "";
    }
  }

  async function handleSaveReview() {
    if (!selectedDocument) return;
    try {
      setActionMessage("Saving reviewed fields...");
      const updated = await updateReview(selectedDocument.id, {
        title: reviewDraft.title,
        category: reviewDraft.category,
        summary: reviewDraft.summary,
        extracted_keywords: reviewDraft.extracted_keywords
          .split(",")
          .map((value) => value.trim())
          .filter(Boolean),
        review_notes: reviewDraft.review_notes
      });
      setDocuments((current) => mergeDocument(current, updated));
      setActionMessage("Review changes saved.");
    } catch (error) {
      setActionMessage(error instanceof Error ? error.message : "Unable to save review.");
    }
  }

  async function handleFinalize() {
    if (!selectedDocument) return;
    try {
      setActionMessage("Finalizing document...");
      const updated = await finalizeDocument(selectedDocument.id, approverName, finalNotes);
      setDocuments((current) => mergeDocument(current, updated));
      setActionMessage("Document finalized and ready for export.");
    } catch (error) {
      setActionMessage(error instanceof Error ? error.message : "Unable to finalize document.");
    }
  }

  async function handleRetry() {
    if (!selectedDocument) return;
    try {
      setActionMessage("Retrying failed document...");
      await retryDocument(selectedDocument.id);
      await refreshDocuments();
      setActionMessage("Retry queued successfully.");
    } catch (error) {
      setActionMessage(error instanceof Error ? error.message : "Retry failed.");
    }
  }

  return (
    <main className={styles.pageShell}>
      <section className={styles.hero}>
        <div className={styles.heroCopy}>
          <span className={styles.badge}>Async Document Processing Workflow</span>
          <h1>DocFlow Studio</h1>
          <p>
            Production-style document orchestration with FastAPI, Celery, Redis Pub/Sub, PostgreSQL, live progress
            visibility, review workflows, retry handling, and export-ready results.
          </p>
          <div className={styles.heroStats}>
            <article>
              <strong>{documents.length}</strong>
              <span>Total Jobs</span>
            </article>
            <article>
              <strong>{activeJobs}</strong>
              <span>Live Running</span>
            </article>
            <article>
              <strong>{totalProcessed}</strong>
              <span>Review Ready</span>
            </article>
            <article>
              <strong>{totalFailures}</strong>
              <span>Failures</span>
            </article>
          </div>
        </div>
        <div className={styles.heroPanel}>
          <div className={styles.uploadCard}>
            <p className={styles.uploadEyebrow}>Drop files and trigger real background work</p>
            <label className={styles.uploadZone}>
              <input type="file" multiple onChange={handleUpload} disabled={uploading} />
              <span>{uploading ? "Uploading..." : "Upload one or more documents"}</span>
              <small>TXT, PDF, DOC, or any sample file for metadata extraction demo</small>
            </label>
            <div className={styles.actionStrip}>
              <span className={styles.liveDot} />
              <p>{actionMessage}</p>
            </div>
          </div>
        </div>
      </section>

      <section className={styles.contentGrid}>
        <div className={styles.leftColumn}>
          <div className={styles.panel}>
            <div className={styles.panelHeader}>
              <div>
                <p className={styles.panelEyebrow}>Dashboard</p>
                <h2>Queued, processing, completed, failed</h2>
              </div>
              <button className={styles.secondaryButton} onClick={() => void refreshDocuments()}>
                {loadingDocuments ? "Refreshing..." : "Refresh"}
              </button>
            </div>

            <div className={styles.filters}>
              <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Search by file name, title, summary"
              />
              <select value={status} onChange={(event) => setStatus(event.target.value as DocumentStatus | "all")}>
                <option value="all">All status</option>
                <option value="queued">Queued</option>
                <option value="processing">Processing</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
                <option value="finalized">Finalized</option>
              </select>
              <select value={sort} onChange={(event) => setSort(event.target.value)}>
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="name">Name</option>
                <option value="progress">Progress</option>
              </select>
            </div>

            <div className={styles.documentList}>
              {documents.map((document) => (
                <button
                  key={document.id}
                  className={`${styles.documentCard} ${selectedId === document.id ? styles.documentCardActive : ""}`}
                  onClick={() => setSelectedId(document.id)}
                >
                  <div className={styles.documentCardTop}>
                    <span className={`${styles.statusPill} ${statusTone[document.status]}`}>{document.status}</span>
                    <span className={styles.documentTime}>{formatDate(document.created_at)}</span>
                  </div>
                  <h3>{document.title || document.original_name}</h3>
                  <p>{document.summary || "Structured review details will appear here after processing."}</p>
                  <div className={styles.progressRow}>
                    <div className={styles.progressBar}>
                      <span style={{ width: `${document.progress_percent}%` }} />
                    </div>
                    <strong>{document.progress_percent}%</strong>
                  </div>
                </button>
              ))}
              {!documents.length && (
                <div className={styles.emptyState}>
                  <h3>No documents yet</h3>
                  <p>Upload a file to start the async workflow and live progress tracking demo.</p>
                </div>
              )}
            </div>
          </div>
        </div>

        <div className={styles.rightColumn}>
          {selectedDocument ? (
            <>
              <div className={styles.panel}>
                <div className={styles.panelHeader}>
                  <div>
                    <p className={styles.panelEyebrow}>Review Workspace</p>
                    <h2>{selectedDocument.original_name}</h2>
                  </div>
                  <span className={`${styles.statusPill} ${statusTone[selectedDocument.status]}`}>
                    {selectedDocument.status}
                  </span>
                </div>

                <div className={styles.detailGrid}>
                  <label>
                    <span>Title</span>
                    <input
                      value={reviewDraft.title}
                      onChange={(event) => setReviewDraft((current) => ({ ...current, title: event.target.value }))}
                    />
                  </label>
                  <label>
                    <span>Category</span>
                    <input
                      value={reviewDraft.category}
                      onChange={(event) => setReviewDraft((current) => ({ ...current, category: event.target.value }))}
                    />
                  </label>
                  <label className={styles.fullWidth}>
                    <span>Summary</span>
                    <textarea
                      rows={4}
                      value={reviewDraft.summary}
                      onChange={(event) => setReviewDraft((current) => ({ ...current, summary: event.target.value }))}
                    />
                  </label>
                  <label className={styles.fullWidth}>
                    <span>Keywords</span>
                    <input
                      value={reviewDraft.extracted_keywords}
                      onChange={(event) =>
                        setReviewDraft((current) => ({ ...current, extracted_keywords: event.target.value }))
                      }
                      placeholder="comma separated"
                    />
                  </label>
                  <label className={styles.fullWidth}>
                    <span>Review Notes</span>
                    <textarea
                      rows={4}
                      value={reviewDraft.review_notes}
                      onChange={(event) =>
                        setReviewDraft((current) => ({ ...current, review_notes: event.target.value }))
                      }
                    />
                  </label>
                </div>

                <div className={styles.actions}>
                  <button className={styles.primaryButton} onClick={() => void handleSaveReview()}>
                    Save Review
                  </button>
                  <button
                    className={styles.secondaryButton}
                    onClick={() => window.open(exportUrl(selectedDocument.id, "json"), "_blank")}
                  >
                    Export JSON
                  </button>
                  <button
                    className={styles.secondaryButton}
                    onClick={() => window.open(exportUrl(selectedDocument.id, "csv"), "_blank")}
                  >
                    Export CSV
                  </button>
                  {selectedDocument.status === "failed" ? (
                    <button className={styles.warningButton} onClick={() => void handleRetry()}>
                      Retry Job
                    </button>
                  ) : null}
                </div>
              </div>

              <div className={styles.panel}>
                <div className={styles.panelHeader}>
                  <div>
                    <p className={styles.panelEyebrow}>Extracted Output</p>
                    <h2>Full structured details</h2>
                  </div>
                  <span className={styles.metaText}>Parsed from uploaded file</span>
                </div>
                <div className={styles.outputGrid}>
                  {selectedDocument.final_output &&
                  typeof selectedDocument.final_output === "object" &&
                  "details" in selectedDocument.final_output &&
                  selectedDocument.final_output.details &&
                  typeof selectedDocument.final_output.details === "object" ? (
                    Object.entries(selectedDocument.final_output.details as Record<string, unknown>).map(
                      ([key, value]) => (
                        <article key={key} className={styles.outputCard}>
                          <span>{key.replaceAll("_", " ")}</span>
                          <strong>{String(value)}</strong>
                        </article>
                      )
                    )
                  ) : (
                    <div className={styles.emptyState}>
                      <h3>No detailed fields yet</h3>
                      <p>Complete processing first to see extracted PDF or text details.</p>
                    </div>
                  )}
                </div>
                <div className={styles.rawTextBox}>
                  <span className={styles.panelEyebrow}>Parsed Text Preview</span>
                  <pre>{selectedDocument.parsed_text || "No parsed text available yet."}</pre>
                </div>
              </div>

              <div className={styles.panel}>
                <div className={styles.panelHeader}>
                  <div>
                    <p className={styles.panelEyebrow}>Finalize Result</p>
                    <h2>Approve reviewed output</h2>
                  </div>
                  <span className={styles.metaText}>Only completed documents should be finalized</span>
                </div>
                <div className={styles.detailGrid}>
                  <label>
                    <span>Approver Name</span>
                    <input value={approverName} onChange={(event) => setApproverName(event.target.value)} />
                  </label>
                  <label className={styles.fullWidth}>
                    <span>Final Notes</span>
                    <textarea rows={3} value={finalNotes} onChange={(event) => setFinalNotes(event.target.value)} />
                  </label>
                </div>
                <button
                  className={styles.primaryButton}
                  onClick={() => void handleFinalize()}
                  disabled={selectedDocument.status !== "completed" && selectedDocument.status !== "finalized"}
                >
                  Finalize Document
                </button>
              </div>

              <div className={styles.panel}>
                <div className={styles.panelHeader}>
                  <div>
                    <p className={styles.panelEyebrow}>Progress Timeline</p>
                    <h2>Redis Pub/Sub live updates</h2>
                  </div>
                  <span className={styles.metaText}>{isPending ? "Syncing..." : "Live"}</span>
                </div>
                <div className={styles.timeline}>
                  {selectedDocument.events.map((eventItem) => (
                    <div key={`${eventItem.id}-${eventItem.created_at}`} className={styles.timelineItem}>
                      <div className={styles.timelineBullet} />
                      <div>
                        <strong>{eventItem.event_type}</strong>
                        <p>{eventItem.message}</p>
                        <small>
                          {eventItem.progress_percent}% | {formatDate(eventItem.created_at)}
                        </small>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </>
          ) : (
            <div className={styles.panel}>
              <h2>Select a document</h2>
              <p>The review details, progress timeline, finalize controls, and export actions will appear here.</p>
            </div>
          )}
        </div>
      </section>
    </main>
  );
}
