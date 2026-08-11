# REST API 実装（/api/v1）

## 概要

`docs/api-spec.md` の全エンドポイントを PHP で実装した。外部ルーターライブラリを使わず、正規表現マッチングによるシンプルなルーターを自前実装。

## 実装内容

- `src/Container.php` — 依存性注入コンテナ（シングルトン管理）
- `src/Api/Router.php` — API ルーター（正規表現ルート定義・ディスパッチ）
- `src/Api/Controller/AbstractController.php` — 共通レスポンス出力・エラー処理
- `src/Api/Controller/NoteController.php` — ノート CRUD・添付ファイルアップロード
- `src/Api/Controller/NotebookController.php` — ノートブック CRUD
- `src/Api/Controller/TagController.php` — タグ一覧・削除
- `src/Api/Controller/AttachmentController.php` — 添付ファイル取得・削除
- `src/Api/Controller/ExportController.php` — JSON・Markdown ZIP エクスポート
- `src/Api/Controller/HealthController.php` — ヘルスチェック
- `public/index.php` — API / Web ルーティングの振り分けを追加

## 使い方

```bash
# ノート一覧
curl http://localhost/api/v1/notes

# ノート作成（Markdown がデフォルト）
curl -X POST http://localhost/api/v1/notes \
  -H "Content-Type: application/json" \
  -d '{"title":"テスト","content":"# Hello","tags":["tag1"]}'

# ヘルスチェック
curl http://localhost/api/v1/health
```

## 技術的な補足

- ルート定義は `Router::registerRoutes()` に集中。クロージャで各コントローラーを遅延生成する
- `AbstractController::error()` / `json()` は `exit` で終了するため `never` 返り値型を持つ
- `NoteController::create()` の `content_type` デフォルトは `markdown`
- BASIC 認証は #7 で追加。現時点では `public/index.php` にコメントアウトで記載済み
- `Container` はリクエストごとに再生成される（スタティックキャッシュなし）。DB 接続はリクエスト内でシングルトン
