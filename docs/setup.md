# SQNote セットアップガイド

## 必要な環境

| 要件 | バージョン |
|---|---|
| PHP | 8.2 以上 |
| PHP 拡張 | pdo_sqlite（SQLCipher 対応版）、zip |
| Composer | 2.x |
| Web サーバー | Apache 2.4 / Nginx / PHP 組み込みサーバー |

> **重要**: 標準の `pdo_sqlite` は SQLCipher に対応していません。SQLCipher 対応版の PHP を用意する必要があります（下記参照）。

---

## 1. SQLCipher 対応 PHP の準備

### macOS（Homebrew）

```bash
brew install sqlcipher
brew install php   # PHP 8.2 以上

# PHP が SQLCipher でビルドされていることを確認
php -r "new PDO('sqlite::memory:'); echo 'OK';"
```

Homebrew の PHP は通常 SQLite バンドルのため、SQLCipher を使うには PHP を SQLCipher リンクでビルドし直すか、下記の Docker を利用してください。

### Docker（推奨）

SQLCipher 対応済みの環境をすぐに使えます。

```bash
# docker-compose.yml を作成
cat > docker-compose.yml << 'EOF'
services:
  app:
    image: php:8.2-apache
    volumes:
      - .:/var/www/html
    ports:
      - "8080:80"
    environment:
      - SQNOTE_DB_PATH=/var/www/html/data/notes.sqnote
      - SQNOTE_DB_PASS=your-secure-passphrase
      - SQNOTE_BASIC_USER=admin
      - SQNOTE_BASIC_PASS=your-password
    command: >
      bash -c "apt-get update &&
               apt-get install -y libsqlcipher-dev &&
               docker-php-ext-configure pdo_sqlite --with-pdo-sqlite=/usr &&
               docker-php-ext-install pdo_sqlite zip &&
               apache2-foreground"
EOF
```

### Ubuntu / Debian

```bash
sudo apt-get update
sudo apt-get install -y php8.2 php8.2-cli php8.2-zip libsqlcipher-dev

# pdo_sqlite を SQLCipher でビルド（要 phpize）
sudo apt-get install -y php8.2-dev
pecl install pdo_sqlite  # SQLCipher リンクオプションで
```

> SQLCipher 対応ビルドが難しい場合は、開発環境では暗号化なし（`PRAGMA key` が無視される通常 SQLite）でも動作確認はできます。ただし本番環境では必ず SQLCipher を使用してください。

---

## 2. リポジトリのクローンと依存インストール

```bash
git clone https://github.com/tokitam/SQNote.git
cd SQNote
composer install
```

---

## 3. 環境変数の設定

`.env.example` をコピーして `.env` を作成します。

```bash
cp .env.example .env
```

`.env` を編集します。

```dotenv
# データベースファイルのパス（.sqnote はどこに置いても OK）
SQNOTE_DB_PATH=/path/to/data/notes.sqnote

# データベース暗号化パスフレーズ（必須・空文字不可）
SQNOTE_DB_PASS=your-secure-passphrase

# Web UI の BASIC 認証ユーザー名
SQNOTE_BASIC_USER=admin

# Web UI の BASIC 認証パスワード（必須・空文字不可）
SQNOTE_BASIC_PASS=your-basic-auth-password
```

> `SQNOTE_DB_PASS` を空にすると起動時にエラーになります。必ず設定してください。

---

## 4. 起動

### PHP 組み込みサーバー（開発用）

```bash
# .env を読み込んでから起動
export $(grep -v '^#' .env | xargs)
php -S localhost:8080 -t public public/index.php
```

> `public/index.php` をルータースクリプトとして指定することで、すべてのリクエストが `index.php` 経由になります。末尾を省略すると `.htaccess` の RewriteRule が効かず、`/notes/new` などで 404 になります。

ブラウザで `http://localhost:8080` を開くと BASIC 認証が表示されます。

### Apache

`public/` をドキュメントルートに設定し、`mod_rewrite` を有効にします。

```apache
<VirtualHost *:80>
    ServerName sqnote.local
    DocumentRoot /path/to/SQNote/public

    <Directory /path/to/SQNote/public>
        AllowOverride All
        Require all granted
    </Directory>

    # 環境変数を設定
    SetEnv SQNOTE_DB_PATH /path/to/data/notes.sqnote
    SetEnv SQNOTE_DB_PASS your-secure-passphrase
    SetEnv SQNOTE_BASIC_USER admin
    SetEnv SQNOTE_BASIC_PASS your-basic-auth-password
</VirtualHost>
```

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Nginx + PHP-FPM

```nginx
server {
    listen 80;
    server_name sqnote.local;
    root /path/to/SQNote/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;

        # BASIC 認証ヘッダーを PHP に渡す（PHP-FPM 必須設定）
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;

        # 環境変数
        fastcgi_param SQNOTE_DB_PATH /path/to/data/notes.sqnote;
        fastcgi_param SQNOTE_DB_PASS your-secure-passphrase;
        fastcgi_param SQNOTE_BASIC_USER admin;
        fastcgi_param SQNOTE_BASIC_PASS your-basic-auth-password;
    }
}
```

---

## 5. 初回アクセスの確認

初回アクセス時に自動で以下が実行されます。

1. `.sqnote` データベースファイルの新規作成
2. マイグレーション（テーブル作成）の適用

ブラウザで以下を確認してください。

| URL | 説明 |
|---|---|
| `http://localhost:8080/` | ノート一覧 |
| `http://localhost:8080/notes/new` | ノート作成 |
| `http://localhost:8080/import` | .enex インポート |
| `http://localhost:8080/export` | エクスポート |

API の疎通確認:

```bash
curl -u admin:your-basic-auth-password http://localhost:8080/api/v1/health
# {"ok":true,"data":{"status":"ok"}}
```

---

## 6. Evernote データのインポート

Evernote からエクスポートした `.enex` ファイルをインポートします。

```bash
export $(grep -v '^#' .env | xargs)

# 基本インポート
php sqnote import:enex ~/Desktop/MyNotes.enex

# ノートブック名を指定
php sqnote import:enex --notebook="Evernote移行" ~/Desktop/MyNotes.enex

# 書き込まずに確認だけ
php sqnote import:enex --dry-run ~/Desktop/MyNotes.enex

# 重複スキップ
php sqnote import:enex --skip-duplicates ~/Desktop/MyNotes.enex
```

ブラウザからインポートする場合は `http://localhost:8080/import` を使います。

---

## 7. バックアップと復元

`.sqnote` ファイルをコピーするだけでバックアップできます。

```bash
# バックアップ
cp data/notes.sqnote ~/backup/notes_$(date +%Y%m%d).sqnote

# 復元（.env の SQNOTE_DB_PATH を変更するか、ファイルを上書きする）
cp ~/backup/notes_20260811.sqnote data/notes.sqnote
```

ブラウザからは `http://localhost:8080/export` → 「データベースファイル（.sqnote）をダウンロード」でバックアップできます。

> 復元時は同じ `SQNOTE_DB_PASS` が必要です。パスフレーズを忘れると復元できません。

---

## トラブルシューティング

### `SQNOTE_DB_PASS が設定されていません` エラー

`.env` の `SQNOTE_DB_PASS` が空になっています。必ず設定してください。

### `指定されたファイルは SQNote データベースではありません` エラー

- パスフレーズが間違っている
- SQLCipher 非対応の PHP で暗号化済みファイルを開こうとしている
- 別のアプリで作成した SQLite ファイルを指定している

### BASIC 認証が効かない（PHP-FPM 環境）

`public/.htaccess` に以下が含まれていることを確認してください。Apache の場合 `mod_rewrite` が必要です。

```apache
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

Nginx の場合は `fastcgi_param HTTP_AUTHORIZATION $http_authorization;` を設定してください。
