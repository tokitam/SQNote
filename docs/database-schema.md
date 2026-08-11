# SQNote データベーススキーマ

**スキーマバージョン: 1.0.0**

SQLCipher 暗号化 SQLite 3 データベース（`.sqnote` ファイル）のテーブル定義。

## テーブル一覧

| テーブル | 用途 |
|---|---|
| `meta` | DBメタ情報（アプリ識別・スキーマバージョン） |
| `notebooks` | ノートブック（フォルダ相当） |
| `notes` | ノート本体 |
| `tags` | タグ |
| `note_tags` | ノートとタグの中間テーブル |
| `attachments` | 添付ファイル（画像・ファイル） |
| `schema_migrations` | マイグレーション履歴 |

## DDL

### meta

```sql
CREATE TABLE meta (
    key   TEXT NOT NULL PRIMARY KEY,
    value TEXT NOT NULL
);

-- 必須レコード
INSERT INTO meta VALUES ('app_name',       'SQNote');
INSERT INTO meta VALUES ('schema_version', '1.0.0');
INSERT INTO meta VALUES ('created_at',     strftime('%s', 'now'));
```

### notebooks

```sql
CREATE TABLE notebooks (
    id         TEXT    NOT NULL PRIMARY KEY,  -- UUID v4
    name       TEXT    NOT NULL,
    created_at INTEGER NOT NULL,              -- Unix timestamp (秒)
    updated_at INTEGER NOT NULL,
    is_deleted INTEGER NOT NULL DEFAULT 0,
    deleted_at INTEGER
);

CREATE INDEX idx_notebooks_is_deleted ON notebooks (is_deleted);
```

### notes

```sql
CREATE TABLE notes (
    id           TEXT    NOT NULL PRIMARY KEY,  -- UUID v4
    notebook_id  TEXT    REFERENCES notebooks(id),
    title        TEXT    NOT NULL DEFAULT '',
    content      TEXT    NOT NULL DEFAULT '',
    content_type TEXT    NOT NULL DEFAULT 'markdown',  -- 'markdown' | 'html'
    source_url   TEXT,
    created_at   INTEGER NOT NULL,                 -- Unix timestamp (秒)
    updated_at   INTEGER NOT NULL,
    is_deleted   INTEGER NOT NULL DEFAULT 0,
    deleted_at   INTEGER,
    CHECK (content_type IN ('html', 'markdown'))
);

CREATE INDEX idx_notes_notebook_id  ON notes (notebook_id);
CREATE INDEX idx_notes_is_deleted   ON notes (is_deleted);
CREATE INDEX idx_notes_updated_at   ON notes (updated_at DESC);
CREATE INDEX idx_notes_created_at   ON notes (created_at DESC);
```

### tags

```sql
CREATE TABLE tags (
    id         TEXT    NOT NULL PRIMARY KEY,  -- UUID v4
    name       TEXT    NOT NULL UNIQUE,
    created_at INTEGER NOT NULL
);

CREATE INDEX idx_tags_name ON tags (name);
```

### note_tags

```sql
CREATE TABLE note_tags (
    note_id TEXT NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
    tag_id  TEXT NOT NULL REFERENCES tags(id)  ON DELETE CASCADE,
    PRIMARY KEY (note_id, tag_id)
);

CREATE INDEX idx_note_tags_tag_id ON note_tags (tag_id);
```

### attachments

```sql
CREATE TABLE attachments (
    id          TEXT    NOT NULL PRIMARY KEY,  -- UUID v4
    note_id     TEXT    NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
    filename    TEXT    NOT NULL,
    mime_type   TEXT    NOT NULL,
    data        BLOB    NOT NULL,              -- バイナリデータ本体
    hash_sha256 TEXT    NOT NULL,             -- データの SHA-256 ハッシュ（整合性確認用）
    file_size   INTEGER NOT NULL,             -- バイト数
    created_at  INTEGER NOT NULL
);

CREATE INDEX idx_attachments_note_id ON attachments (note_id);
```

### schema_migrations

```sql
CREATE TABLE schema_migrations (
    version    TEXT    NOT NULL PRIMARY KEY,  -- 例: '1.0.0', '1.1.0'
    applied_at INTEGER NOT NULL
);
```

## 外部キー制約

SQLite はデフォルトで外部キー制約が無効のため、接続時に必ず有効化する。

```sql
PRAGMA foreign_keys = ON;
```

## SQLCipher 設定

データベースオープン直後（クエリ実行前）にパスフレーズを設定する。

```sql
PRAGMA key = 'ユーザーが設定したパスフレーズ';
```

その他の SQLCipher パラメータはデフォルト値（AES-256-CBC、PBKDF2-HMAC-SHA512、kdf_iter=256000）を使用する。

## マイグレーション方針

1. アプリ起動時に `meta.schema_version` と実装がサポートする最新バージョンを比較する
2. DBバージョンが古い場合は `schema_migrations` 未適用のマイグレーションを順番に実行する
3. マイグレーション完了後に `meta.schema_version` と `schema_migrations` を更新する
4. マイグレーションは後方互換性を破壊しない変更を優先する（カラム追加は OK、カラム削除は非推奨）

## 全文検索

将来的に FTS5 仮想テーブルを追加する可能性がある（スキーマ v1.1.0 以降で検討）。

```sql
-- 将来実装（v1.1.0 候補）
CREATE VIRTUAL TABLE notes_fts USING fts5(
    title,
    content,
    content='notes',
    content_rowid='rowid'
);
```
