# フォーム認証（セッション）実装

## 概要

BASIC 認証をフォームベースのセッション認証へ切り替えた。ログイン画面（ID・パスワード入力）で認証し、セッションクッキーで状態を保持することでスマートフォンからも快適に利用できる。

## 実装内容

- `src/Auth/SessionAuth.php` — セッション管理クラス（新規）
- `src/Web/Controller/AuthController.php` — ログイン・ログアウトコントローラー（新規）
- `src/Web/Router.php` — `/login`, `/logout` ルート追加
- `templates/auth/login.html.twig` — ログインフォームテンプレート（新規）
- `templates/layout.html.twig` — ヘッダーに「ログアウト」リンクを追加
- `public/index.php` — 認証フローをセッション認証優先・BASIC 認証フォールバックに変更
- `public/assets/css/app.css` — ログインフォーム用スタイル追加
- `config/config.php` — `session` 設定ブロックを追加

## 使い方

1. ブラウザで任意のページにアクセスすると `/login` にリダイレクトされる
2. `.env` に設定した `SQNOTE_BASIC_USER` / `SQNOTE_BASIC_PASS` の値でログインする
3. セッションは 7 日間有効（`SQNOTE_SESSION_LIFETIME` 環境変数で秒単位で変更可）
4. ヘッダー右端の「ログアウト」をクリックするとセッションを破棄して `/login` へ戻る

## 技術的な補足

### 認証の優先順位

1. セッションクッキー（`sqnote_sess`）が有効 → 認証済み
2. BASIC 認証ヘッダーが正しい → 認証済み（API クライアント向けの後方互換）
3. 上記いずれも不正 → Web リクエストは `/login` へリダイレクト、API は JSON 401

### CSRF 対策

ログインフォームに CSRF トークンを埋め込む。`POST /login` の処理時に `hash_equals()` で照合し、不一致なら 403 を返す。

### セッションクッキーのセキュリティ設定

| 属性 | 値 |
|---|---|
| `HttpOnly` | true（JS からアクセス不可） |
| `SameSite` | Lax |
| `Secure` | HTTPS 環境のみ true |
| 有効期限 | デフォルト 7 日 |

### セッションファイルの保存先

PHP 標準のファイルセッションを使用。サーバーのデフォルト `session.save_path`（多くの場合 `/tmp`）を利用する。本番環境では Web 公開ディレクトリ外のパスを `session_save_path()` で指定することを推奨。
