# 履歴差分表示

## 概要

履歴一覧ページの各バージョン行に「差分」リンクを追加し、専用の差分表示ページで現在のノートと選択した履歴バージョンを行単位で比較できるようにした。

## 実装内容

| ファイル | 変更内容 |
|---|---|
| `src/Web/Controller/NoteHistoryController.php` | `diff()` メソッドを追加 |
| `src/Web/Router.php` | `/notes/{id}/history/{hid}/diff` ルートを追加 |
| `templates/note/diff.html.twig` | 差分表示テンプレートを新規作成 |
| `templates/note/history.html.twig` | 各行に「差分」リンクを追加 |
| `docs/17_note_history_diff.md` | 本ドキュメント |

## 使い方

1. ノート詳細画面の「履歴」をクリックして履歴一覧を開く
2. 各バージョン行の「差分」リンクをクリックする
3. 差分表示ページで、選択したバージョンと現在のノートを比較できる
   - タイトルが変わっていれば変更前後を並べて表示
   - 本文は行単位で差分表示（緑: 追加、赤: 削除）
4. 「このバージョンへ戻す」ボタンで差分ページからも復元可能

## 技術的な補足

- diff 計算はクライアントサイド JavaScript（jsdiff v7、CDN）で行う
- `Diff.diffLines()` で本文の行単位差分、`Diff.diffWords()` でタイトルの単語単位差分を計算
- CDN が読み込めないオフライン環境では差分表示の代わりにエラーメッセージを表示する
- `/history/{hid}/diff` は `/history/{hid}` よりルーター登録を先にすることで誤マッチを防止
