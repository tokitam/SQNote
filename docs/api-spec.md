# SQNote REST API 仕様

フロントエンドとバックエンドの通信仕様。バックエンドがどの言語で実装されていても、この仕様に準拠したエンドポイントを提供する。

## 基本仕様

| 項目 | 値 |
|---|---|
| ベースパス | `/api/v1` |
| レスポンス形式 | JSON |
| 文字コード | UTF-8 |
| 認証 | BASIC 認証（v1 実装） |
| 日時フォーマット | ISO 8601 UTC（`2026-08-11T00:00:00Z`） |

## 共通レスポンス形式

### 成功

```json
{
  "ok": true,
  "data": { ... }
}
```

リスト取得の場合は `data` が配列、かつページネーション情報を含む。

```json
{
  "ok": true,
  "data": [ ... ],
  "meta": {
    "total": 100,
    "limit": 20,
    "offset": 0
  }
}
```

### エラー

```json
{
  "ok": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Note not found"
  }
}
```

| HTTP ステータス | エラーコード | 意味 |
|---|---|---|
| 400 | `INVALID_PARAM` | パラメータ不正 |
| 401 | `UNAUTHORIZED` | BASIC 認証失敗 |
| 404 | `NOT_FOUND` | リソースが存在しない |
| 409 | `CONFLICT` | 競合（重複など） |
| 500 | `INTERNAL_ERROR` | サーバー内部エラー |

## エンドポイント

### ノートブック

#### ノートブック一覧取得

```
GET /api/v1/notebooks
```

**レスポンス**

```json
{
  "ok": true,
  "data": [
    {
      "id": "uuid",
      "name": "仕事",
      "note_count": 42,
      "created_at": "2026-08-11T00:00:00Z",
      "updated_at": "2026-08-11T00:00:00Z"
    }
  ]
}
```

#### ノートブック作成

```
POST /api/v1/notebooks
```

**リクエストボディ**

```json
{
  "name": "新しいノートブック"
}
```

#### ノートブック更新

```
PUT /api/v1/notebooks/{id}
```

**リクエストボディ**

```json
{
  "name": "変更後の名前"
}
```

#### ノートブック削除

```
DELETE /api/v1/notebooks/{id}
```

ノートブック内のノートが存在する場合はエラー（`CONFLICT`）を返す。

---

### ノート

#### ノート一覧取得

```
GET /api/v1/notes
```

**クエリパラメータ**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `notebook_id` | string | - | ノートブックで絞り込み |
| `tag` | string | - | タグ名で絞り込み |
| `q` | string | - | タイトル・本文の全文検索 |
| `limit` | integer | 20 | 取得件数（最大 100） |
| `offset` | integer | 0 | オフセット |
| `sort` | string | `updated_at` | ソートキー（`updated_at` / `created_at` / `title`） |
| `order` | string | `desc` | 昇順/降順（`asc` / `desc`） |

**レスポンス**

```json
{
  "ok": true,
  "data": [
    {
      "id": "uuid",
      "title": "ノートタイトル",
      "notebook_id": "uuid",
      "notebook_name": "ノートブック名",
      "tags": ["tag1", "tag2"],
      "content_type": "markdown",
      "created_at": "2026-08-11T00:00:00Z",
      "updated_at": "2026-08-11T00:00:00Z",
      "has_attachments": true
    }
  ],
  "meta": {
    "total": 100,
    "limit": 20,
    "offset": 0
  }
}
```

一覧取得では `content` は返さない（パフォーマンス考慮）。

#### ノート詳細取得

```
GET /api/v1/notes/{id}
```

**レスポンス**

```json
{
  "ok": true,
  "data": {
    "id": "uuid",
    "title": "ノートタイトル",
    "content": "<div>本文</div>",
    "content_type": "html",
    "notebook_id": "uuid",
    "notebook_name": "ノートブック名",
    "tags": ["tag1", "tag2"],
    "source_url": "https://example.com",
    "created_at": "2026-08-11T00:00:00Z",
    "updated_at": "2026-08-11T00:00:00Z",
    "attachments": [
      {
        "id": "uuid",
        "filename": "image.png",
        "mime_type": "image/png",
        "file_size": 102400,
        "url": "/api/v1/attachments/uuid"
      }
    ]
  }
}
```

#### ノート作成

```
POST /api/v1/notes
```

**リクエストボディ**

```json
{
  "title": "タイトル",
  "content": "<div>本文</div>",
  "content_type": "html",
  "notebook_id": "uuid",
  "tags": ["tag1", "tag2"],
  "source_url": "https://example.com"
}
```

`content_type` のデフォルトは `markdown`。`notebook_id` 省略時はデフォルトノートブックに格納。

#### ノート更新

```
PUT /api/v1/notes/{id}
```

リクエストボディはノート作成と同じ。送信したフィールドのみ更新する（PATCH 的な動作）。

#### ノート削除

```
DELETE /api/v1/notes/{id}
```

ソフトデリート（`is_deleted = 1`）を実行する。物理削除は行わない。

---

### タグ

#### タグ一覧取得

```
GET /api/v1/tags
```

**レスポンス**

```json
{
  "ok": true,
  "data": [
    {
      "id": "uuid",
      "name": "tag1",
      "note_count": 10,
      "created_at": "2026-08-11T00:00:00Z"
    }
  ]
}
```

#### タグ削除

```
DELETE /api/v1/tags/{id}
```

タグを削除すると `note_tags` の関連レコードも削除される（CASCADE）。

---

### 添付ファイル

#### 添付ファイル取得

```
GET /api/v1/attachments/{id}
```

バイナリデータを `Content-Type` ヘッダーつきで返す。

#### 添付ファイルアップロード

```
POST /api/v1/notes/{note_id}/attachments
```

`multipart/form-data` でファイルを送信する。

**レスポンス**

```json
{
  "ok": true,
  "data": {
    "id": "uuid",
    "filename": "image.png",
    "mime_type": "image/png",
    "file_size": 102400,
    "url": "/api/v1/attachments/uuid"
  }
}
```

#### 添付ファイル削除

```
DELETE /api/v1/attachments/{id}
```

---

### エクスポート

#### JSON エクスポート

```
GET /api/v1/export/json
```

**クエリパラメータ**

| パラメータ | 説明 |
|---|---|
| `notebook_id` | 特定ノートブックのみエクスポート |
| `note_id` | 特定ノートのみエクスポート |

全件エクスポートはパラメータなしで実行。レスポンスは `application/json`。

#### Markdown エクスポート

```
GET /api/v1/export/markdown
```

Markdown ファイル群を ZIP 形式で返す（`application/zip`）。
ノートブックごとにディレクトリを作成し、ノートを `.md` ファイルとして格納する。

---

### ステータス

#### ヘルスチェック

```
GET /api/v1/health
```

```json
{
  "ok": true,
  "data": {
    "app_name": "SQNote",
    "schema_version": "1.0.0",
    "db_path": "/path/to/notes.sqnote"
  }
}
```
