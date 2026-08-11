# スキーマ DDL・マイグレーション基盤実装

## 概要

全テーブルの DDL（`migrations/1.0.0.sql`）と、スキーマバージョンを管理するマイグレーション基盤（`Migrator`）を実装した。アプリ起動時に自動で未適用マイグレーションを実行する。

## 実装内容

- `migrations/1.0.0.sql` — 全テーブル（notebooks / notes / tags / note_tags / attachments / schema_migrations）の DDL
- `src/Database/Migrator.php` — マイグレーション実行・適用済みバージョン管理

## 使い方

```php
use SQNote\Database\Connection;
use SQNote\Database\Migrator;

$conn     = new Connection($dbPath, $passphrase);
$migrator = new Migrator($conn->getPdo(), __DIR__ . '/migrations');
$migrator->run();
```

`run()` はアプリ起動時（`public/index.php` と `sqnote` CLI の両方）に必ず呼ぶ。

## 技術的な補足

- マイグレーションファイルはバージョン文字列（`version_compare`）でソートして順番に適用する
- 各マイグレーションはトランザクション単位で実行し、失敗時は自動ロールバックする
- `schema_migrations` テーブルが存在しない場合（初回 DB 作成直後）は `getAppliedVersions()` が空配列を返して全マイグレーションを適用する
- 将来のスキーマ変更は `migrations/1.1.0.sql` 等を追加するだけで自動適用される
- `notes.content_type` のデフォルトは `'markdown'`（新規作成ノートの標準形式）
