# Web UI 基盤実装（レイアウト・ルーティング・ノート一覧）

## 概要

Twig テンプレートエンジンを使った Web UI の基盤を実装。サイドバー・メインエリアの 2 ペインレイアウト、ノート一覧・詳細・検索・ノートブック・タグ表示画面を追加した。

## 実装内容

- `src/Web/Router.php` — Web URL のルーティング
- `src/Web/Controller/AbstractController.php` — Twig レンダリング・リダイレクト基底
- `src/Web/Controller/NoteController.php` — 一覧・詳細・検索
- `src/Web/Controller/NotebookController.php` — ノートブック一覧・詳細
- `src/Web/Controller/TagController.php` — タグ一覧・タグ絞り込み
- `templates/layout.html.twig` — 共通レイアウト
- `templates/note/` — ノート一覧・詳細・リスト部品テンプレート
- `templates/notebook/` — ノートブックテンプレート
- `templates/tag/` — タグテンプレート
- `public/assets/css/app.css` — CSS（カスタムプロパティベース）
- `public/index.php` — Web ルーターを組み込み

## 技術的な補足

- `markdown` ノートは `league/commonmark` で変換。`html_input => 'strip'` で Markdown 内の生 HTML を除去（XSS 対策）
- `html` ノートは `strip_tags()` で許可タグ以外を除去してから Twig の `|raw` フィルターで出力
- ページネーションは 20 件/ページ固定、`?page=N` クエリパラメータで制御
- Twig のキャッシュは `false`（開発しやすさ優先。本番では `__DIR__ . '/../var/cache/twig'` 等に変更を推奨）
