CREATE TABLE IF NOT EXISTS notebooks (
    id         TEXT    NOT NULL PRIMARY KEY,
    name       TEXT    NOT NULL,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    is_deleted INTEGER NOT NULL DEFAULT 0,
    deleted_at INTEGER
);
CREATE INDEX IF NOT EXISTS idx_notebooks_is_deleted ON notebooks (is_deleted);

CREATE TABLE IF NOT EXISTS notes (
    id           TEXT    NOT NULL PRIMARY KEY,
    notebook_id  TEXT    REFERENCES notebooks(id),
    title        TEXT    NOT NULL DEFAULT '',
    content      TEXT    NOT NULL DEFAULT '',
    content_type TEXT    NOT NULL DEFAULT 'markdown',
    source_url   TEXT,
    created_at   INTEGER NOT NULL,
    updated_at   INTEGER NOT NULL,
    is_deleted   INTEGER NOT NULL DEFAULT 0,
    deleted_at   INTEGER,
    CHECK (content_type IN ('html', 'markdown'))
);
CREATE INDEX IF NOT EXISTS idx_notes_notebook_id ON notes (notebook_id);
CREATE INDEX IF NOT EXISTS idx_notes_is_deleted  ON notes (is_deleted);
CREATE INDEX IF NOT EXISTS idx_notes_updated_at  ON notes (updated_at DESC);
CREATE INDEX IF NOT EXISTS idx_notes_created_at  ON notes (created_at DESC);

CREATE TABLE IF NOT EXISTS tags (
    id         TEXT    NOT NULL PRIMARY KEY,
    name       TEXT    NOT NULL UNIQUE,
    created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_tags_name ON tags (name);

CREATE TABLE IF NOT EXISTS note_tags (
    note_id TEXT NOT NULL REFERENCES notes(id)  ON DELETE CASCADE,
    tag_id  TEXT NOT NULL REFERENCES tags(id)   ON DELETE CASCADE,
    PRIMARY KEY (note_id, tag_id)
);
CREATE INDEX IF NOT EXISTS idx_note_tags_tag_id ON note_tags (tag_id);

CREATE TABLE IF NOT EXISTS attachments (
    id          TEXT    NOT NULL PRIMARY KEY,
    note_id     TEXT    NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
    filename    TEXT    NOT NULL,
    mime_type   TEXT    NOT NULL,
    data        BLOB    NOT NULL,
    hash_sha256 TEXT    NOT NULL,
    file_size   INTEGER NOT NULL,
    created_at  INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_attachments_note_id ON attachments (note_id);

CREATE TABLE IF NOT EXISTS schema_migrations (
    version    TEXT    NOT NULL PRIMARY KEY,
    applied_at INTEGER NOT NULL
);
