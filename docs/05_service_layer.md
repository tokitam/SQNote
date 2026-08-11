# Service 層実装

## 概要

ビジネスロジックを担う 3 つの Service クラスを実装した。Repository を組み合わせてトランザクション管理・ドメインルールの強制・エクスポート処理を行う。

## 実装内容

- `src/Service/NoteService.php` — ノート作成・更新・削除・添付ファイル管理
- `src/Service/NotebookService.php` — ノートブック作成・更新・削除（ノートが存在する場合は削除を拒否）
- `src/Service/ExportService.php` — JSON エクスポート・Markdown ZIP エクスポート

## 技術的な補足

### トランザクション

`NoteService::create()` と `update()` はタグの `findOrCreate` と `attachTag` をまとめてトランザクション内で実行する。失敗時は自動ロールバック。

### タグの更新戦略

`update()` でタグを更新する際は既存タグを全削除して付け直す（差分更新ではない）。シンプルな実装を優先しており、タグ件数が数十件以内の用途では問題ない。

### インポート日時の引き継ぎ

`NoteService::create()` は `$data['created_at']` / `$data['updated_at']` に Unix タイムスタンプを渡すと元の日時を使う。省略時は `time()`。

### エクスポート

- `ExportService::toJson()` は `docs/data-format.md` の JSON フォーマット準拠。添付ファイルのバイナリは Base64 エンコードして `data_base64` フィールドに含める
- `ExportService::toMarkdownZip()` は `ZipArchive` で一時ファイルに書き出し、バイナリ文字列を返す。ノートブック名をディレクトリ名とし、ファイルシステム非対応文字は `_` に置換する
- `html` ノートは `.html`、`markdown` ノートは `.md` として出力する
