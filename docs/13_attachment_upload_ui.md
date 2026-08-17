# 添付ファイル UI（短期実装）設計書

## 概要

既存の CodeMirror 6 エディタ（`templates/note/edit.html.twig`）を最小限に変更し、  
画像・ファイルの添付とインライン挿入を実現する。ビルドステップなし・外部ライブラリ追加なし。

---

## 変更ファイル一覧

| ファイル | 変更種別 | 内容 |
|---|---|---|
| `templates/note/edit.html.twig` | 変更 | 添付ボタン・添付パネル・hidden input 追加 |
| `public/assets/css/app.css` | 変更 | 添付パネル・トースト用スタイル追加 |
| `src/Api/Controller/NoteController.php` | 変更 | MIME タイプ検証を追加 |

---

## API（既存・変更なし）

| メソッド | パス | 用途 |
|---|---|---|
| `POST` | `/api/v1/notes/{note_id}/attachments` | アップロード（multipart, フィールド名 `file`） |
| `GET` | `/api/v1/attachments/{id}` | バイナリ配信 |
| `DELETE` | `/api/v1/attachments/{id}` | 削除 |

アップロードレスポンス（HTTP 201）:

```json
{ "ok": true, "data": { "id": "UUID", "filename": "photo.png", "mime_type": "image/png", "url": "/api/v1/attachments/UUID" } }
```

---

## HTML 変更（`edit.html.twig`）

### 1. ツールバーに添付ボタンを追加

変更前:
```html
<div class="editor-actions">
  <button class="btn btn-primary" id="save-btn" data-note-id="{{ note ? note.id : '' }}">保存</button>
  <a href="{{ note ? '/notes/' ~ note.id : '/' }}" class="btn">キャンセル</a>
</div>
```

変更後:
```html
<div class="editor-actions">
  <button class="btn btn-primary" id="save-btn" data-note-id="{{ note ? note.id : '' }}">保存</button>
  <a href="{{ note ? '/notes/' ~ note.id : '/' }}" class="btn">キャンセル</a>
  <button class="btn" id="attach-btn" type="button">📎 添付</button>
  <input type="file" id="attach-input" multiple
         accept="image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,text/csv,application/zip"
         style="display:none">
</div>
```

### 2. 添付パネルをエディタ下部に追加

`<div id="save-status" ...>` の後ろに追加:

```html
<div id="attach-panel" class="attach-panel" style="{{ note and note.attachments|length > 0 ? '' : 'display:none' }}">
  <div class="attach-panel-header">
    添付ファイル <span id="attach-count">{{ note ? note.attachments|length : 0 }}</span> 件
  </div>
  <ul class="attach-list" id="attach-list">
    {% if note %}
      {% for att in note.attachments %}
        <li class="attach-item" data-id="{{ att.id }}">
          {% if att.mime_type starts with 'image/' %}
            <img src="{{ att.url }}" class="attach-thumb" alt="{{ att.filename }}">
          {% else %}
            <span class="attach-icon">📄</span>
          {% endif %}
          <span class="attach-name" title="{{ att.filename }}">{{ att.filename }}</span>
          <button class="attach-delete" type="button" data-id="{{ att.id }}" title="削除">×</button>
        </li>
      {% endfor %}
    {% endif %}
  </ul>
  <div class="attach-drop-hint">ここにファイルをドロップ</div>
</div>

<div id="attach-toast" class="attach-toast" style="display:none"></div>
```

### 3. ドラッグ&ドロップ受け付け領域

エディタ本体（`#editor-cm` または `#editor-md`）に `data-drop-target="1"` を付与する。  
JS 側でこの属性を持つ要素に `dragover` / `drop` イベントを登録する。

---

## JavaScript 設計

### 全体構造

```
edit.html.twig の <script> ブロックに追記
（既存の saveNote / addEventListener と同じスコープ内）

attachState               … モジュール内状態
uploadFile(file)          … 1ファイルをアップロードしてパネルに追加
ensureNoteId()            … NOTE_ID が空なら自動保存して ID を確定
insertMarkdown(md)        … CodeMirror / textarea のカーソル位置に挿入
addPanelItem(att)         … 添付パネルに 1 件追加
removePanelItem(id)       … 添付パネルから 1 件削除
showToast(msg, type)      … 一時通知（success / error）
```

### 状態

```javascript
const attachState = {
  uploading: false,  // アップロード中フラグ
};
```

### `ensureNoteId()` — 新規ノートの自動保存

NOTE_ID が空（新規ノート）の場合にのみ呼ぶ。  
既存 `saveNote()` を流用するが、保存後に `window.location.href` へ遷移させる行を止める必要がある。

方針: `saveNote()` の戻り値に `id` を返すようリファクタリングし、  
`ensureNoteId()` では遷移せず `NOTE_ID` と URL だけ更新する。

```javascript
// saveNote() の戻り値を id に変更（既存コードの修正点）
// 現行: if (!NOTE_ID) window.location.href = '/notes/' + data.data.id;
// 変更: NOTE_ID = data.data.id; history.pushState({}, '', `/notes/${NOTE_ID}/edit`); return NOTE_ID;

async function ensureNoteId() {
  if (NOTE_ID) return NOTE_ID;
  return await saveNote();  // saveNote() が id を返すように変更後
}
```

### `uploadFile(file)` — メインアップロード処理

```javascript
async function uploadFile(file) {
  const MAX = 20 * 1024 * 1024;
  if (file.size > MAX) {
    showToast(`${file.name} は 20MB を超えています`, 'error');
    return;
  }

  if (attachState.uploading) return;
  attachState.uploading = true;
  document.getElementById('attach-btn').disabled = true;

  try {
    const noteId = await ensureNoteId();
    if (!noteId) { showToast('ノートの保存に失敗しました', 'error'); return; }

    const form = new FormData();
    form.append('file', file);

    const res = await fetch(`/api/v1/notes/${noteId}/attachments`, {
      method: 'POST',
      credentials: 'same-origin',
      body: form,
    });

    const json = await res.json();
    if (!res.ok || !json.ok) {
      showToast('アップロード失敗: ' + (json.error?.message ?? res.status), 'error');
      return;
    }

    const att = json.data;                          // { id, filename, mime_type, url }
    addPanelItem(att);

    const isImage = att.mime_type.startsWith('image/');
    const md = isImage
      ? `![${att.filename}](${att.url})`
      : `[${att.filename}](${att.url})`;
    insertMarkdown(md);

    showToast(`${att.filename} を添付しました`, 'success');
  } catch (e) {
    showToast('エラー: ' + e.message, 'error');
  } finally {
    attachState.uploading = false;
    document.getElementById('attach-btn').disabled = false;
  }
}
```

### `insertMarkdown(md)` — カーソル位置に挿入

```javascript
function insertMarkdown(md) {
  // CodeMirror が有効な場合
  if (window._cmView) {
    const view = window._cmView;
    const { from } = view.state.selection.main;
    view.dispatch({ changes: { from, insert: md } });
    view.focus();
    return;
  }
  // フォールバック: textarea
  const ta = document.getElementById('editor-md');
  if (!ta) return;
  const s = ta.selectionStart;
  ta.value = ta.value.slice(0, s) + md + ta.value.slice(ta.selectionEnd);
  ta.selectionStart = ta.selectionEnd = s + md.length;
  ta.focus();
}
```

> `window._cmView` は CodeMirror 初期化時に `window._cmView = view;` と設定する（既存の `window._editor` とは別に追加）。

### `addPanelItem(att)` — パネルに追加

```javascript
function addPanelItem(att) {
  const panel = document.getElementById('attach-panel');
  const list  = document.getElementById('attach-list');
  const count = document.getElementById('attach-count');

  const li = document.createElement('li');
  li.className = 'attach-item';
  li.dataset.id = att.id;

  if (att.mime_type.startsWith('image/')) {
    const img = document.createElement('img');
    img.src = att.url;
    img.className = 'attach-thumb';
    img.alt = att.filename;
    // サムネイルクリックでカーソルに再挿入
    img.addEventListener('click', () => insertMarkdown(`![${att.filename}](${att.url})`));
    li.appendChild(img);
  } else {
    const icon = document.createElement('span');
    icon.className = 'attach-icon';
    icon.textContent = '📄';
    li.appendChild(icon);
  }

  const name = document.createElement('span');
  name.className = 'attach-name';
  name.title = att.filename;
  name.textContent = att.filename;
  li.appendChild(name);

  const del = document.createElement('button');
  del.className = 'attach-delete';
  del.type = 'button';
  del.textContent = '×';
  del.dataset.id = att.id;
  del.addEventListener('click', () => deleteAttachment(att.id));
  li.appendChild(del);

  list.appendChild(li);
  count.textContent = list.children.length;
  panel.style.display = '';
}
```

### `deleteAttachment(id)` — 削除

```javascript
async function deleteAttachment(id) {
  if (!confirm('添付ファイルを削除しますか？')) return;
  const res = await fetch(`/api/v1/attachments/${id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
  });
  if (res.ok) {
    removePanelItem(id);
  } else {
    showToast('削除に失敗しました', 'error');
  }
}
```

### `removePanelItem(id)`

```javascript
function removePanelItem(id) {
  const li    = document.querySelector(`#attach-list [data-id="${id}"]`);
  const count = document.getElementById('attach-count');
  const panel = document.getElementById('attach-panel');
  if (li) li.remove();
  const remaining = document.getElementById('attach-list').children.length;
  count.textContent = remaining;
  if (remaining === 0) panel.style.display = 'none';
}
```

### `showToast(msg, type)`

```javascript
function showToast(msg, type = 'success') {
  const toast = document.getElementById('attach-toast');
  toast.textContent = msg;
  toast.className   = `attach-toast attach-toast--${type}`;
  toast.style.display = '';
  clearTimeout(toast._timer);
  toast._timer = setTimeout(() => { toast.style.display = 'none'; }, 3000);
}
```

### イベント登録

```javascript
// ツールバーボタン
document.getElementById('attach-btn').addEventListener('click', () => {
  document.getElementById('attach-input').click();
});

// ファイル選択ダイアログ
document.getElementById('attach-input').addEventListener('change', e => {
  for (const file of e.target.files) uploadFile(file);
  e.target.value = '';  // 同一ファイルの再選択を許可
});

// ペースト（エディタにフォーカスがある状態で画像をペースト）
document.addEventListener('paste', e => {
  const files = Array.from(e.clipboardData?.files ?? []);
  const images = files.filter(f => f.type.startsWith('image/'));
  if (images.length === 0) return;
  e.preventDefault();
  images.forEach(uploadFile);
});

// ドラッグ&ドロップ（エディタ領域全体）
const dropTargets = [
  document.getElementById('editor-cm'),
  document.getElementById('editor-md'),
  document.getElementById('attach-panel'),
].filter(Boolean);

dropTargets.forEach(el => {
  el.addEventListener('dragover', e => {
    e.preventDefault();
    el.classList.add('drag-over');
  });
  el.addEventListener('dragleave', () => el.classList.remove('drag-over'));
  el.addEventListener('drop', e => {
    e.preventDefault();
    el.classList.remove('drag-over');
    Array.from(e.dataTransfer.files).forEach(uploadFile);
  });
});

// 既存の添付削除ボタン（Twig で描画済みのもの）
document.querySelectorAll('.attach-delete[data-id]').forEach(btn => {
  btn.addEventListener('click', () => deleteAttachment(btn.dataset.id));
});
```

---

## CSS 追加（`app.css`）

```css
/* 添付パネル */
.attach-panel {
  border: 1px solid var(--color-border);
  border-radius: 4px;
  margin-top: 12px;
  background: var(--color-surface);
}
.attach-panel-header {
  font-size: 12px;
  color: var(--color-muted);
  padding: 6px 12px;
  border-bottom: 1px solid var(--color-border);
}
.attach-list {
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 10px 12px;
  min-height: 36px;
}
.attach-item {
  display: flex;
  align-items: center;
  gap: 4px;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  padding: 4px 6px;
  font-size: 12px;
  background: #fafafa;
  max-width: 180px;
}
.attach-thumb {
  width: 40px;
  height: 40px;
  object-fit: cover;
  border-radius: 2px;
  cursor: pointer;
  flex-shrink: 0;
}
.attach-icon { font-size: 20px; }
.attach-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100px;
}
.attach-delete {
  border: none;
  background: none;
  color: var(--color-muted);
  cursor: pointer;
  padding: 0 2px;
  font-size: 14px;
  line-height: 1;
  flex-shrink: 0;
}
.attach-delete:hover { color: var(--color-danger); }
.attach-drop-hint {
  font-size: 11px;
  color: var(--color-muted);
  padding: 4px 12px 8px;
  text-align: center;
}

/* ドラッグオーバー */
.drag-over { outline: 2px dashed var(--color-accent); outline-offset: -2px; }

/* トースト通知 */
.attach-toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  padding: 10px 16px;
  border-radius: 6px;
  font-size: 13px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  z-index: 200;
}
.attach-toast--success { background: #1a7f37; color: #fff; }
.attach-toast--error   { background: var(--color-danger); color: #fff; }
```

---

## サーバーサイド変更（`NoteController::createAttachment()`）

MIME タイプ検証を追加する。

```php
private const ALLOW_MIMES = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    'application/pdf',
    'text/plain', 'text/csv', 'text/markdown',
    'application/zip',
];

// createAttachment() 内、$mime 確定直後に追加
if (!in_array($mime, self::ALLOW_MIMES, true)) {
    $this->error('INVALID_PARAM', '許可されていないファイル形式です', 422);
}
```

---

## `saveNote()` の変更点

新規ノート保存後に自動遷移しないよう変更し、`note_id` を返すようにする。

```javascript
// 変更前
if (!NOTE_ID) window.location.href = '/notes/' + data.data.id;

// 変更後
if (!NOTE_ID) {
  NOTE_ID = data.data.id;
  history.pushState({}, '', `/notes/${NOTE_ID}/edit`);
}
return NOTE_ID;  // 追加
```

`saveNote()` の戻り値は現状 `undefined` なので、`try` ブロック末尾で `return NOTE_ID;` を追加する。

---

## 制約・スコープ外

| 項目 | 対応 |
|---|---|
| ノート詳細ページ（`/notes/:id`）での添付表示 | 既存の `note.attachments` で表示済み。追加変更なし |
| 画像リサイズ | 対応しない |
| ファイル合計サイズ制限 | 対応しない（1ファイル 20MB のみ） |
| ドラッグ順序の変更 | 対応しない |
| オフライン対応 | 対応しない |
