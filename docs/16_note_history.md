# ノート履歴（バージョン管理）

## 概要

ノートを更新するたびに更新前の内容をスナップショットとして保存し、過去バージョンの閲覧・復元を可能にする機能。

## 実装内容

### 追加ファイル

| ファイル | 役割 |
|---|---|
| `migrations/1.1.0.sql` | `note_history` テーブルとインデックスを追加 |
| `src/Repository/NoteHistoryRepository.php` | 履歴の保存・一覧取得・詳細取得 |
| `src/Api/Controller/NoteHistoryController.php` | 履歴一覧・詳細・復元 API |
| `src/Web/Controller/NoteHistoryController.php` | 履歴一覧 Web 画面 |
| `templates/note/history.html.twig` | 履歴一覧テンプレート |

### 変更ファイル

| ファイル | 変更内容 |
|---|---|
| `src/Service/NoteService.php` | `update()` の冒頭で更新前内容を履歴保存 |
| `src/Container.php` | `noteHistoryRepository()` を追加、`noteService()` に注入 |
| `src/Api/Router.php` | 履歴 API エンドポイントを登録 |
| `src/Web/Router.php` | 履歴 Web ルートを登録 |
| `templates/note/show.html.twig` | アクションバーに「履歴」リンクを追加 |

## 使い方

1. ノートを編集・保存すると、更新前の内容が自動的に履歴として記録される
2. ノート詳細画面の「履歴」ボタンをクリックすると、過去バージョンの一覧が表示される
3. 一覧から「このバージョンへ戻す」をクリックすると、現在のノートが選択したバージョンの内容に上書きされる（復元時の内容も新しい履歴として記録される）

## API エンドポイント

| メソッド | パス | 説明 |
|---|---|---|
| GET | `/api/v1/notes/{id}/history` | 履歴一覧（`id, version, title, saved_at` のみ） |
| GET | `/api/v1/notes/{id}/history/{hid}` | 指定バージョンの全内容 |
| POST | `/api/v1/notes/{id}/history/{hid}/restore` | 指定バージョンへ復元 |

## 技術的な補足

- 履歴は `note_history` テーブルに保持し、`notes` テーブルは変更しない（後方互換性を維持）
- 保持上限は 50 件。超過時は最も古いものを自動削除
- 一覧 API では `content` を返さず、詳細取得時のみ返すことで通信量を抑制
- 復元は既存の `NoteService::update()` を流用するため、復元した事実も履歴に記録される
