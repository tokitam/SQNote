CREATE TABLE IF NOT EXISTS note_history (
    id           TEXT    NOT NULL PRIMARY KEY,
    note_id      TEXT    NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
    title        TEXT    NOT NULL DEFAULT '',
    content      TEXT    NOT NULL DEFAULT '',
    content_type TEXT    NOT NULL DEFAULT 'markdown',
    version      INTEGER NOT NULL,
    saved_at     INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_note_history_note_id ON note_history (note_id, version DESC);
