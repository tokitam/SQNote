# SQNote

SQLite（SQLCipher 暗号化）をメインDBとして使用するノートアプリ。データファイルを1つコピーするだけでバックアップ・リストアができる。

<img width="1448" height="1086" alt="ChatGPT Image 2026年8月26日 11_18_24" src="https://github.com/user-attachments/assets/13163af8-74b2-4a71-95a9-a5dcfe6a4103" />

## 特徴

- **ポータビリティ**: `.sqnote` ファイル（SQLCipher 暗号化 SQLite DB）を1ファイルコピーするだけでバックアップ・リストア完了
- **言語非依存**: データフォーマット仕様を公開し、任意の言語で実装可能
- **Evernote 移行**: `.enex` ファイルからのインポートに対応
- **シンプルな認証**: BASIC 認証で画面を保護

## 必要な環境

| 要件 | バージョン |
|---|---|
| PHP | 8.2 以上 |
| PHP 拡張 | pdo_sqlite（SQLCipher 対応版）、zip |
| Composer | 2.x |

## セットアップ

```bash
git clone https://github.com/tokitam/SQNote.git
cd SQNote
composer install
cp .env.example .env
# .env を編集し、DBパス・パスフレーズ・BASIC認証情報を設定

export $(grep -v '^#' .env | xargs)
php -S localhost:8080 -t public public/index.php
```

ブラウザで `http://localhost:8080` を開く。

詳細な手順は [docs/setup.md](docs/setup.md) を参照。

## Evernote データのインポート

```bash
export $(grep -v '^#' .env | xargs)
php sqnote import:enex ~/Desktop/MyNotes.enex
```

## ドキュメント

- [docs/overview.md](docs/overview.md) — プロジェクト概要
- [docs/setup.md](docs/setup.md) — セットアップガイド
- [docs/architecture.md](docs/architecture.md) — システムアーキテクチャ
- [docs/data-format.md](docs/data-format.md) — データフォーマット仕様
- [docs/api-spec.md](docs/api-spec.md) — REST API 仕様

## ライセンス

MIT
