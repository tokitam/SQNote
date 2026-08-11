# SQNote システムアーキテクチャ（PHP v1 実装）

## ディレクトリ構成

```
SQNote/
├── docs/                   # 設計書（本ディレクトリ）
├── src/
│   ├── Database/
│   │   ├── Connection.php  # SQLCipher 接続管理
│   │   └── Migrator.php    # スキーママイグレーション
│   ├── Repository/
│   │   ├── NoteRepository.php
│   │   ├── NotebookRepository.php
│   │   ├── TagRepository.php
│   │   └── AttachmentRepository.php
│   ├── Service/
│   │   ├── NoteService.php
│   │   ├── ExportService.php
│   │   └── ImportService.php
│   ├── Api/
│   │   └── Controller/     # REST API コントローラー
│   ├── Web/
│   │   └── Controller/     # Web 画面コントローラー
│   ├── Importer/
│   │   └── EnexImporter.php
│   └── Cli/
│       └── ImportCommand.php
├── templates/              # PHP テンプレート（Web UI）
├── public/
│   ├── index.php           # エントリーポイント
│   └── assets/             # CSS, JS, 画像
├── data/
│   └── notes.sqnote        # SQLCipher 暗号化 DB（.gitignore 対象）
├── config/
│   └── config.php          # 設定ファイル
├── sqnote                  # CLI エントリーポイント（実行可能）
└── composer.json
```

## コンポーネント構成

```
┌──────────────────────────────────────────────────────┐
│                    クライアント                        │
│  ブラウザ (Web UI)  /  CLI (import コマンド)           │
└──────────────┬──────────────────┬────────────────────┘
               │ HTTP             │ CLI
               ▼                  ▼
┌──────────────────────┐  ┌──────────────────────┐
│   Web Controller     │  │   CLI Command         │
│   (PHP / Twig)       │  │   (import:enex)       │
└──────────┬───────────┘  └──────────┬────────────┘
           │                          │
           ▼                          ▼
┌──────────────────────────────────────────────────────┐
│                    Service Layer                      │
│  NoteService / ExportService / ImportService          │
└──────────────────────────┬───────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────┐
│                  Repository Layer                     │
│  NoteRepo / NotebookRepo / TagRepo / AttachmentRepo   │
└──────────────────────────┬───────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────┐
│                  Database Layer                       │
│  Connection (PDO + SQLCipher)  /  Migrator            │
└──────────────────────────┬───────────────────────────┘
                           │
                           ▼
                  ┌─────────────────┐
                  │  notes.sqnote   │
                  │  (SQLCipher DB) │
                  └─────────────────┘
```

## 設定管理

`config/config.php` で管理する。機密情報は環境変数から読み込む。

```php
return [
    'db' => [
        'path'       => getenv('SQNOTE_DB_PATH') ?: __DIR__ . '/../data/notes.sqnote',
        'passphrase' => getenv('SQNOTE_DB_PASS'),  // 必須・デフォルトなし
    ],
    'auth' => [
        'user' => getenv('SQNOTE_BASIC_USER') ?: 'admin',
        'pass' => getenv('SQNOTE_BASIC_PASS'),      // 必須・デフォルトなし
    ],
];
```

## 依存ライブラリ（Composer）

| ライブラリ | 用途 |
|---|---|
| `staudenmeir/sqlite-wal` or PDO_SQLCipher | SQLCipher 接続 |
| `twig/twig` | テンプレートエンジン（オプション） |
| `ramsey/uuid` | UUID v4 生成 |
| `symfony/console` | CLI コマンドフレームワーク |

## PHP バージョン

PHP 8.2 以上を対象とする。

## Web サーバー設定

### Apache (.htaccess)

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]

# BASIC 認証
AuthType Basic
AuthName "SQNote"
AuthUserFile /path/to/.htpasswd
Require valid-user
```

### Nginx

```nginx
server {
    root /path/to/SQNote/public;
    index index.php;

    auth_basic "SQNote";
    auth_basic_user_file /path/to/.htpasswd;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        include fastcgi_params;
    }
}
```

## セキュリティ考慮事項

| 項目 | 対策 |
|---|---|
| DB ファイル | SQLCipher で暗号化。パスフレーズは環境変数で管理し、設定ファイルにハードコードしない |
| Web 認証 | BASIC 認証（HTTPS 必須） |
| DB ファイルパス | `public/` ディレクトリ外に配置し、Web から直接アクセス不可にする |
| SQL インジェクション | PDO プリペアドステートメントを必ず使用 |
| XSS | テンプレートでエスケープ。本文 HTML は許可タグリストでサニタイズ |
| CSRF | フォーム送信時に CSRF トークンを検証 |

## 将来的な言語実装の追加

バックエンドを別言語で実装する場合の必要要件。

1. SQLCipher 対応の SQLite ドライバーを使用する
2. `data-format.md` のデータフォーマット仕様に準拠する
3. `database-schema.md` のスキーマを使用してDBを初期化する
4. `api-spec.md` のエンドポイントを実装する
5. スキーママイグレーション機能を実装する

実装が揃い次第、各言語の実装を別ディレクトリ（例: `implementations/typescript/`）として本リポジトリに追加することを検討する。
