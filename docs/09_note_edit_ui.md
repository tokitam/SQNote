# Web UI ノート作成・編集画面実装（Markdown エディタ）

## 概要

ノートの作成・編集画面を実装した。新規作成は Markdown エディタ（CodeMirror 6）をデフォルト表示し、`html` ノートは WYSIWYG エディタ（Quill.js 2）で編集する。保存は Fetch API 経由で非同期に行う。

## 実装内容

- `src/Web/Controller/NoteEditController.php` — 作成・編集・保存コントローラー
- `src/Web/Router.php` — 編集ルート追加（`GET /notes/new`, `POST /notes`, `GET /notes/{id}/edit`, `POST /notes/{id}`）
- `templates/note/edit.html.twig` — 編集テンプレート（エディタ切り替え・タグオートコンプリート）
- `public/assets/css/app.css` — エディタ用スタイル追加

## 使い方

- `GET /notes/new` — 新規作成画面（Markdown エディタ）
- `GET /notes/{id}/edit` — 既存ノート編集（`content_type` に応じてエディタを切り替え）
- **Cmd+S / Ctrl+S** でキーボードショートカット保存
- タグ入力フィールドは datalist で既存タグをサジェスト

## 技術的な補足

### エディタ選択

| `content_type` | エディタ | CDN |
|---|---|---|
| `markdown` | CodeMirror 6 + `@codemirror/lang-markdown` | `esm.sh` ESM ビルド |
| `html` | Quill.js 2 | `jsdelivr` |

### 保存フロー

1. `saveNote()` 関数がエディタ内容を取得（`window._editor.getContent()`）
2. Fetch API で `POST /api/v1/notes`（新規）または `PUT /api/v1/notes/{id}`（更新）を呼ぶ
3. 新規作成成功後は `/notes/{id}` にリダイレクト
4. 更新成功後は「保存しました」ステータスを表示（ページ遷移なし）

### フォームサブミットフォールバック

`NoteEditController::store()` / `update()` は JS 非対応環境向けに `$_POST` からも保存できる。タグはカンマ区切りの文字列として送信する。

### CodeMirror の ESM ロード

CDN（`esm.sh`）から ESM モジュールとして読み込む。`<script type="module">` が必要。
