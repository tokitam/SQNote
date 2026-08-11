# Web UI インポート・エクスポート画面実装

## 概要

ブラウザから `.enex` ファイルをアップロードしてインポートする画面と、各種フォーマットでデータをエクスポートする画面を実装した。

## 実装内容

- `src/Web/Controller/ImportController.php` — インポート画面コントローラ
- `src/Web/Controller/ExportController.php` — エクスポート画面コントローラ
- `src/Web/Router.php` — `/import` / `/export` ルート追加
- `templates/import/index.html.twig` — インポート画面
- `templates/export/index.html.twig` — エクスポート画面

## 使い方

### インポート

1. ブラウザで `/import` にアクセス
2. `.enex` ファイルを選択
3. インポート先ノートブックを選択（または新規作成）
4. 必要に応じて「重複をスキップ」「ドライラン」を ON にする
5. 「インポート開始」ボタンをクリック
6. 結果サマリーが表示される

### エクスポート

1. ブラウザで `/export` にアクセス
2. 以下のいずれかを選択:
   - **JSON エクスポート**: 全ノートまたはノートブック指定で JSON ダウンロード
   - **Markdown ZIP エクスポート**: 全ノートを Markdown 形式の ZIP でダウンロード
   - **データベースバックアップ**: `.sqnote` ファイルをそのままダウンロード

## 技術的な補足

- `.enex` のアップロードは `$_FILES['tmp_name']` を直接 `EnexImporter::import()` に渡す
- バックアップは `readfile()` で DB ファイルをストリーミング送信（暗号化済みのため安全）
- JSON / Markdown ZIP エクスポートは既存の `ExportService` を呼び出す
- ノートブック指定の JSON エクスポートは `notebook_id` クエリパラメータで対応
