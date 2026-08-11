# ファイル添付 UI・エディタ拡張設計

## 概要

ノートに画像やその他ファイルを添付し、Markdown 本文にインライン表示できるようにする。
バックエンド（DB・API・Service）はすでに実装済みのため、フロント側（エディタ統合・UI）の設計が中心。

---

## 現状確認

### 実装済み

| 対象 | 内容 |
|---|---|
| DBスキーマ | `attachments` テーブル（BLOB 保存） |
| `AttachmentRepository` | CRUD |
| `NoteService.addAttachment()` | 添付登録 |
| `POST /api/v1/notes/:id/attachments` | multipart ファイルアップロード |
| `GET /api/v1/attachments/:id` | バイナリ配信 |
| `DELETE /api/v1/attachments/:id` | 削除 |

### 未実装（今回設計するもの）

- エディタ上の画像アップロード UI（ツールバーボタン・ドラッグ&ドロップ・ペースト）
- アップロード後の Markdown 挿入（`![filename](url)` 形式）
- 添付ファイル一覧パネル（画像サムネイル・非画像ファイルリスト）
- ノート詳細ページでの添付ファイル表示
- ファイルサイズ検証

---

## エディタ変更方針

### 問題

現行の CodeMirror 6 はテキストエディタであり、**画像のインライン表示** ができない。
Markdown 本文に `![img](/api/v1/attachments/UUID)` を挿入しても、テキストとして見えるだけ。

### ライブラリ比較

| ライブラリ | 種別 | 出力形式 | 画像対応 | ビルド不要 | 備考 |
|---|---|---|---|---|---|
| **CodeMirror 6** (現行) | コードエディタ | Markdown テキスト | ✗ インライン表示なし | ✓ esm.sh | 現状維持 |
| **Milkdown** | WYSIWYG Markdown | Markdown テキスト | ✓ 画像ドロップ&貼付 | △ esm.sh で利用可だが複雑 | ProseMirror ベース |
| **TipTap** | WYSIWYG | HTML or Markdown | ✓ Image 拡張あり | △ esm.sh で利用可 | 拡張性高いが重め |
| **ByteMD** | 分割プレビュー Markdown | Markdown テキスト | ✓ プラグインで対応 | △ | Hashnode/GitHub 採用 |
| **EasyMDE** | 分割プレビュー Markdown | Markdown テキスト | ✓ ファイルボタン追加可 | ✓ CDN 直接 | SimpleMDE の後継・軽量 |

### 推奨方針

#### 短期（まず実装する）: CodeMirror 6 + 添付パネル分離方式

- エディタは CodeMirror 6 のまま維持
- ツールバーに **「📎 添付」ボタン** を追加
- アップロード後、カーソル位置に `![ファイル名](/api/v1/attachments/UUID)` を挿入
- 画像プレビューは**エディタ下部の添付パネル**で行う（エディタ内ではなし）
- 実装コストが低く、現行の動作を壊さない

#### 中期（オプション）: EasyMDE への移行

- 分割プレビューペイン付き Markdown エディタ
- esm.sh または CDN で `npm:easymde` を読み込み可能
- プレビュー部分で `![img](url)` が画像として描画される
- 画像アップロードコールバックを差し込める API がある
- CodeMirror 6 から比較的移行しやすい（内部は CodeMirror 5）

#### 長期（将来）: Milkdown への移行

- フル WYSIWYG Markdown（見たまま編集、出力は Markdown テキスト）
- 画像の貼り付け・ドロップがネイティブ対応
- ただし esm.sh での利用は設定が複雑なため、ビルドステップ導入が現実的

---

## 添付ファイルアップロードフロー

```
ユーザー操作
    │
    ├─ ツールバー「📎」クリック → input[type=file] を programmatically クリック
    ├─ エディタ領域にファイルをドロップ（dragover + drop イベント）
    └─ エディタにフォーカスした状態で画像をペースト（paste イベント, clipboardData.files）

    ↓ ファイル取得後

    新規ノートの場合
    ├─ ノートを自動保存してから note_id を取得
    └─ 保存失敗時はエラートースト表示

    既存ノートの場合
    └─ そのまま note_id を利用

    ↓

    FormData に file をセット
    POST /api/v1/notes/{note_id}/attachments
    ↓
    200 OK: { id, filename, mime_type, url }
    ↓
    画像の場合 → エディタのカーソル位置に `![filename](url)` を挿入
    非画像の場合 → `[filename](url)` を挿入
    ↓
    添付パネルを再描画
```

---

## UI 設計

### エディタツールバー拡張

現行の「保存」ボタン行に添付ボタンを追加する。

```
[タイトル入力欄]
[ノートブック ▼] [タグ入力]
[保存] [キャンセル] [📎 ファイルを添付]
```

- 「📎 ファイルを添付」クリック → `<input type="file" multiple accept="image/*,application/pdf,...">` をトリガー
- アップロード中はスピナー表示・ボタン無効化

### 添付ファイルパネル

エディタ本体の下部に常時表示するパネル。

```
+------------------------------------------+
| 添付ファイル (3)                           |
+------------------------------------------+
| [🖼 screenshot.png ×]  [🖼 diagram.png ×] |
| [📄 report.pdf ×]                         |
+------------------------------------------+
| ここにファイルをドロップ                    |
+------------------------------------------+
```

- 画像: サムネイル（`<img src="/api/v1/attachments/ID" width="80" height="60" style="object-fit:cover">`）
- 非画像: ファイルアイコン + ファイル名
- `×` クリック → `DELETE /api/v1/attachments/:id` → パネルから除去
- サムネイルクリック → Markdown に `![name](url)` を挿入
- ドロップゾーン: エディタ本体と兼用

### ノート詳細ページ

詳細表示（`/notes/:id`）でも添付パネルを表示する。

```
[ノート本文（Markdown レンダリング）]

────── 添付ファイル ──────
[🖼 image1.png] [📄 document.pdf]
```

- 画像: リンク付きサムネイル（クリックで拡大 or 新規タブ）
- 非画像: ダウンロードリンク

---

## API との対応

```
アップロード:   POST   /api/v1/notes/{note_id}/attachments  (multipart/form-data)
一覧取得:       GET    /api/v1/notes/{note_id}  → レスポンスの attachments 配列を使う
バイナリ取得:   GET    /api/v1/attachments/{id}
削除:           DELETE /api/v1/attachments/{id}
```

新規ノート（note_id 不明）のアップロード対応:

```
saveNote() を呼び出して note_id を確定
→ window.location を /notes/:id/edit に変更せず、URL を history.pushState で更新
→ NOTE_ID 変数を更新してアップロードを続行
```

---

## ファイルサイズ制限（クライアントサイド）

| 区分 | 上限 | 挙動 |
|---|---|---|
| 1 ファイルあたり | 20 MB | 超過時はエラートースト・アップロードをキャンセル |
| ノートあたりの合計 | 制限なし（運用上の注意のみ） | — |

```javascript
const MAX_FILE_BYTES = 20 * 1024 * 1024;
if (file.size > MAX_FILE_BYTES) {
    showError(`${file.name} は 20MB を超えているためアップロードできません`);
    return;
}
```

---

## MIME タイプ制限（サーバーサイド）

現行 API はファイルタイプを検証していない。以下を追加する。

```php
// NoteController::createAttachment() に追加
const ALLOW_MIMES = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    'application/pdf',
    'text/plain', 'text/csv', 'text/markdown',
    'application/zip',
];

if (!in_array($mime, self::ALLOW_MIMES, true)) {
    $this->error('INVALID_PARAM', '許可されていないファイル形式です');
}
```

---

## CodeMirror へのテキスト挿入

```javascript
// view は cm.EditorView のインスタンス
function insertAtCursor(view, text) {
    const { from } = view.state.selection.main;
    view.dispatch({
        changes: { from, to: from, insert: text },
        selection: { anchor: from + text.length },
    });
    view.focus();
}

// 使用例
const url = `/api/v1/attachments/${data.id}`;
const md  = isImage(data.mime_type)
    ? `![${data.filename}](${url})`
    : `[${data.filename}](${url})`;
insertAtCursor(view, md);
```

CodeMirror が読み込めていない（フォールバックのテキストエリア）場合:

```javascript
function insertAtCursor(view, text) {
    const ta = document.getElementById('editor-md');
    const s  = ta.selectionStart;
    ta.value = ta.value.slice(0, s) + text + ta.value.slice(ta.selectionEnd);
    ta.selectionStart = ta.selectionEnd = s + text.length;
}
```

---

## 実装ステップ（参考）

1. **サーバーサイド**: `NoteController::createAttachment()` に MIME 検証を追加
2. **Web UI ツールバー**: `edit.html.twig` に添付ボタン追加
3. **アップロード JS**: fetch multipart → URL 取得 → カーソル挿入
4. **ペースト対応**: `paste` イベントで `clipboardData.files` を処理
5. **ドラッグ&ドロップ**: `dragover` / `drop` イベント
6. **添付パネル**: ノート読み込み時に既存添付を表示、追加/削除でリフレッシュ
7. **ノート詳細ページ**: `show.html.twig` に添付ファイル表示を追加
8. **（オプション）EasyMDE 移行**: 分割プレビューと統合した形でのアップロード

---

## 保留・課題

| 課題 | 内容 |
|---|---|
| 大きな BLOB と SQLCipher | 大きな画像を暗号化 SQLite BLOB に保存すると書き込みが遅い可能性。100KB 以上の画像は書き込みの体感速度を確認して判断する |
| エクスポートへの影響 | JSON / Markdown エクスポート時に添付バイナリをどう扱うか（base64 埋め込み or 別ファイル化）は `ExportService` の拡張で対応 |
| 画像のリサイズ | クライアントサイドで Canvas を使って長辺 2000px 以下にリサイズしてからアップロードする選択肢あり。v1 では対応しない |
| コンテンツハッシュ重複排除 | `attachments.hash_sha256` を使って同一ファイルの重複登録を防ぐ最適化は将来対応 |
