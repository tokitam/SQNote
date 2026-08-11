# Evernote .enex インポーター CLI 実装

## 概要

Evernote からエクスポートした `.enex` ファイルを SQNote DB にインポートする CLI コマンドを実装した。インポートされたノートの `content_type` は `html` で保存する。

## 実装内容

- `src/Importer/EnexImporter.php` — enex パース・ENML→HTML 変換・添付ファイル抽出・DB 書き込み
- `src/Cli/ImportCommand.php` — `import:enex` コマンド（symfony/console）
- `sqnote` — CLI エントリーポイント（実行可能）

## 使い方

```bash
# 基本インポート
php sqnote import:enex ~/Desktop/MyNotes.enex

# インポート先ノートブックを指定
php sqnote import:enex --notebook="Evernote移行" ~/Desktop/MyNotes.enex

# ドライラン（書き込みなし）
php sqnote import:enex --dry-run ~/Desktop/MyNotes.enex

# 重複スキップ＋詳細ログ
php sqnote import:enex --skip-duplicates -v ~/Desktop/MyNotes.enex

# 別の DB ファイルを指定
php sqnote import:enex --db=/path/to/notes.sqnote ~/Desktop/MyNotes.enex
```

## 技術的な補足

### ENML → HTML 変換

| ENML タグ | 変換先 |
|---|---|
| `<en-note>` | 子要素を直接展開（`<div>` ラッパーなし） |
| `<en-media type="image/*">` | `<img src="attachment://{md5hash}" data-sqnote-attachment-id="{md5hash}">` |
| `<en-media type="application/*">` | `<a href="/api/v1/attachments/{md5hash}">{filename}</a>` |
| `<en-todo checked="false">` | `<input type="checkbox" disabled>` |
| `<en-todo checked="true">` | `<input type="checkbox" disabled checked>` |

変換後の `src="attachment://{md5hash}"` は現時点では MD5 ハッシュをプレースホルダーとして使用する。実際のレンダリング時は `attachments` テーブルの `hash_sha256` または別途マッピングが必要（TODO: UUID に置換する処理の追加）。

### リソース抽出

`<resource>` 要素の `data` 要素に改行入り Base64 で格納されたバイナリを `preg_replace('/\s+/', '', ...)` で改行除去してデコードする。MD5 ハッシュで `<en-media>` と対応付ける。

### トランザクション

全ノートを 1 トランザクションで処理。`--dry-run` 時は最後に `rollBack()` を呼んで書き込みなしで終了する。個別ノートのエラーは `errors` カウントに計上してスキップし、全体のロールバックは行わない。

### 重複チェック

`--skip-duplicates` 指定時は同一ノートブック内で `title` と `created_at` が一致するノートをスキップする。
