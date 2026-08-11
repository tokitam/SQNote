# SQNote データフォーマット仕様

**バージョン: 1.0.0**

本仕様が SQNote の核心。この仕様に準拠することで任意の言語・プラットフォームで SQNote 互換の実装が作成できる。

## ファイル形式

| 項目 | 値 |
|---|---|
| 拡張子 | `.sqnote` |
| 実体 | SQLCipher 暗号化 SQLite 3 データベース |
| 暗号化アルゴリズム | AES-256-CBC（SQLCipher デフォルト） |
| スキーマバージョン管理 | `meta` テーブルの `schema_version` キー |

### ファイル識別

`meta` テーブルに以下を必ず格納する。

```
app_name    = "SQNote"
schema_version = "1.0.0"
```

SQLCipher でオープンできないファイル、または `app_name` が "SQNote" でないファイルは SQNote ファイルとして扱わない。

## ノートのコンテンツ形式

本文は HTML または Markdown で格納する。`notes.content_type` で種別を識別する。

**新規作成するノートのデフォルトは `markdown`。**
`html` は Evernote 等外部ツールからのインポートデータを保持するための互換形式として扱う。

| `content_type` | 内容 | 用途 |
|---|---|---|
| `markdown` | CommonMark 準拠 Markdown | 新規ノートのデフォルト |
| `html` | HTML 文字列（Evernote 互換サブセット、後述） | 外部インポートデータの保持 |

### HTML サブセット仕様

Evernote との互換性を維持しつつ、最小限のタグセットを定義する。

**許可するブロック要素**

```
div, p, h1, h2, h3, h4, h5, h6,
ul, ol, li,
blockquote, pre, hr, br,
table, thead, tbody, tr, th, td
```

**許可するインライン要素**

```
span, a, strong, em, u, s, code,
img (src は attachment:// スキームまたは https://)
```

**許可する属性**

- `style`: `color`, `background-color`, `font-size`, `font-weight`, `text-decoration`, `text-align` のみ
- `href` (a タグ)
- `src`, `alt`, `width`, `height` (img タグ)
- `data-sqnote-attachment-id`: 添付ファイル参照用カスタム属性

**添付ファイルの参照**

HTML 本文内で添付ファイルを参照する場合は以下の形式を使用する。

```html
<img src="attachment://{attachment_id}" data-sqnote-attachment-id="{attachment_id}" alt="{filename}" />
```

## データのエクスポート/インポート

### エクスポート形式

SQNote は以下の形式でデータをエクスポートできる。

| 形式 | 用途 |
|---|---|
| `.sqnote` ファイルのコピー | フルバックアップ・リストア（推奨） |
| JSON（後述） | 他システムへのデータ移行、デバッグ |
| Markdown ファイル群（zip） | テキストのみの可視エクスポート |

### JSON エクスポート仕様

ノート1件の JSON 表現。

```json
{
  "sqnote_version": "1.0.0",
  "exported_at": "2026-08-11T00:00:00Z",
  "note": {
    "id": "uuid-v4",
    "title": "ノートタイトル",
    "content": "<div>本文 HTML</div>",
    "content_type": "html",
    "created_at": "2026-08-11T00:00:00Z",
    "updated_at": "2026-08-11T00:00:00Z",
    "notebook": {
      "id": "uuid-v4",
      "name": "ノートブック名"
    },
    "tags": ["tag1", "tag2"],
    "source_url": "https://example.com",
    "attachments": [
      {
        "id": "uuid-v4",
        "filename": "image.png",
        "mime_type": "image/png",
        "data_base64": "base64エンコードされたバイナリデータ",
        "hash_sha256": "sha256ハッシュ値"
      }
    ]
  }
}
```

複数ノートのエクスポートは、`note` キーを `notes` キー（配列）に変更したフォーマットを使用する。

### バックアップ・リストア

**バックアップ**: `.sqnote` ファイルをコピーするだけ。

**リストア**: コピーしたファイルをアプリが参照するパスに戻すだけ。

スキーマバージョンが異なる場合はアプリ起動時にマイグレーションを実行する。

## 実装ガイドライン

新しい言語・プラットフォームで SQNote を実装する際の必須要件。

1. SQLCipher を使用して `.sqnote` ファイルを開く
2. `meta` テーブルの `app_name` = "SQNote" を確認する
3. `meta` テーブルの `schema_version` を確認し、実装がサポートするバージョンと照合する
4. スキーマが古い場合はマイグレーションを実行してから操作する
5. ノート ID は UUID v4 を使用する
6. 日時は UTC の ISO 8601 形式（`YYYY-MM-DDTHH:MM:SSZ`）で扱い、DB には Unix タイムスタンプ（秒）で格納する
7. 添付ファイルのバイナリは `attachments.data` BLOB に直接格納する（外部ファイル参照は使用しない）
8. ソフトデリートを使用する（`is_deleted = 1` にセット、即時物理削除しない）
