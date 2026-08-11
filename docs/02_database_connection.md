# SQLCipher 接続・DB 初期化実装

## 概要

SQLCipher で暗号化された `.sqnote` ファイルへの PDO 接続と、新規 DB 作成時の初期化処理を `Connection` クラスに実装した。

## 実装内容

- `src/Database/Connection.php` — SQLCipher 接続・`meta` テーブル初期化・`app_name` 検証

## 使い方

```php
use SQNote\Database\Connection;

$conn = new Connection('/path/to/notes.sqnote', 'my-passphrase');
$pdo  = $conn->getPdo();
```

## 技術的な補足

### SQLCipher について

標準の `pdo_sqlite` は SQLCipher に未対応。以下の手順でビルドが必要。

**Ubuntu / Debian**

```bash
apt-get install libsqlcipher-dev
# PHP を --with-pdo-sqlite=/usr/lib で再ビルド、または
# SQLCipher 対応の PHP イメージを使用する
```

**Docker（推奨）**

```dockerfile
FROM php:8.2-fpm
RUN apt-get update && apt-get install -y libsqlcipher-dev \
    && docker-php-ext-configure pdo_sqlite --with-pdo-sqlite=/usr \
    && docker-php-ext-install pdo_sqlite
```

### 接続フロー

1. `SQNOTE_DB_PASS` が空なら即座に `RuntimeException`
2. `new PDO('sqlite:...')` でファイルを開く（存在しない場合は SQLite が自動作成）
3. `PRAGMA key = '...'` でパスフレーズ設定（**クエリ実行前に必須**）
4. `PRAGMA journal_mode = WAL` でバックアップ中の整合性を向上
5. `PRAGMA foreign_keys = ON` で外部キー制約を有効化
6. 新規ファイルなら `meta` テーブルを作成して `app_name`, `schema_version`, `created_at` を INSERT
7. 既存ファイルなら `meta.app_name = 'SQNote'` を検証

### パスフレーズが誤っている場合

`PRAGMA key` は誤ったパスフレーズでもエラーを返さない。後続のクエリ（`SELECT FROM meta`）が失敗した時点で `RuntimeException` に変換され「パスフレーズが正しくない可能性があります」のメッセージが表示される。
