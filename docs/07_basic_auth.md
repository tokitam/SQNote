# BASIC 認証実装

## 概要

Web 画面・API 全体を BASIC 認証で保護する `BasicAuth` クラスを実装し、`public/index.php` に組み込んだ。

## 実装内容

- `src/Auth/BasicAuth.php` — BASIC 認証チェック・チャレンジ応答
- `public/index.php` — 全リクエストの先頭で認証を実行

## 使い方

`.env` で認証情報を設定する。

```
SQNOTE_BASIC_USER=admin
SQNOTE_BASIC_PASS=your-strong-password
```

`SQNOTE_BASIC_PASS` が空の場合は**常に認証失敗**になる（素通り禁止）。

## 技術的な補足

### PHP-FPM 環境対応

`$_SERVER['PHP_AUTH_USER']` が空になる PHP-FPM 環境では、`HTTP_AUTHORIZATION` ヘッダーを Base64 デコードして認証情報を取り出す。`public/.htaccess` に以下を記述することで Apache 経由でも動作する。

```apache
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

### タイミング攻撃対策

パスワード比較には `hash_equals()` を使用し、文字列長の違いによる処理時間の差を排除する。

### 未認証時の応答分岐

- `/api/v1/...` へのリクエスト: JSON 形式で 401 を返す
- それ以外: HTML で 401 ページを返す

どちらも `WWW-Authenticate: Basic realm="SQNote"` ヘッダーを付与する。

### HTTPS の必須性

BASIC 認証はパスワードが Base64 のみで平文相当の送信となる。**本番環境では必ず HTTPS を使用すること。**
