# プロジェクト初期セットアップ

## 概要

SQNote のプロジェクト骨格を構築した。ディレクトリ構成・Composer 設定・設定ファイル・エントリーポイントの骨格を作成し、以降の実装 issue がすべてこの構成に乗れる状態にした。

## 実装内容

- ディレクトリ構成の作成（`src/`, `public/`, `config/`, `data/`, `templates/`, `migrations/`）
- `composer.json` — 依存ライブラリの定義（`ramsey/uuid`, `symfony/console`, `twig/twig`, `league/commonmark`）
- `.gitignore` — `data/*.sqnote`, `vendor/`, `.env` を除外
- `.env.example` — 環境変数のテンプレート
- `config/config.php` — 環境変数からの設定読み込み
- `public/index.php` — フロントコントローラーの骨格（API / Web ルーティングは後続 issue で実装）
- `public/.htaccess` — URL リライト・PHP-FPM 向け Authorization ヘッダー転送設定
- `sqnote` — CLI エントリーポイント（`chmod +x` 済み）
- `data/.gitkeep`, `migrations/.gitkeep`, `templates/.gitkeep` — 空ディレクトリをリポジトリに含めるための gitkeep

## 使い方

```bash
# 依存ライブラリのインストール
composer install

# 環境変数の設定
cp .env.example .env
# .env を編集して SQNOTE_DB_PASS 等を設定

# Web サーバーのドキュメントルートを public/ に向ける
```

## 技術的な補足

- PHP 8.2 以上が必要
- `league/commonmark` は #8（Web UI 基盤）で Markdown レンダリングに使用するため、`composer.json` に先行追加済み
- `SQNOTE_DB_PASS` が空のまま起動した場合、後続の `Connection` クラス（#2 で実装）が `RuntimeException` を投げて明示的にエラーになる設計
- BASIC 認証は `SQNOTE_BASIC_PASS` が空のまま起動した場合、#7 実装後は必ず認証失敗となる（素通り禁止）
