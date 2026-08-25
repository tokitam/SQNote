# 保存フィードバックと blur 自動保存

## 概要
編集画面（`/notes/{id}/edit`）で、保存操作に対する視覚フィードバックを明確にし、エディタからフォーカスが外れた（blur）タイミングで変更内容を自動保存するようにした。従来は保存ボタンを押しても `#save-status` にテキストが出るだけで反応が分かりづらかった点を改善する。

## 実装内容
- `public/assets/css/app.css`
  - `.save-status` に `transition: opacity 0.5s` を追加し、既定色を `--color-text` に変更。
  - `.save-status.hidden { opacity: 0; }`（フェードアウト用）、`.save-status.error { color: var(--color-danger); }`（失敗表示）、`.save-status.muted { color: var(--color-muted); }`（自動保存表示）を追加。
- `templates/note/edit.html.twig`
  - `showStatus(msg, { error, muted, persist })` ヘルパーを追加。メッセージを表示し、`persist` でない場合は 2 秒後に `hidden` クラスを付けてフェードアウトさせる。
  - `saveNote(auto = false)` を `showStatus()` ベースに書き換え。手動保存は「保存中...」→「保存しました」、自動保存は「自動保存中...」→「自動保存しました」（グレー）を表示。失敗時は赤色で表示。
  - 保存成功時に `_lastSavedContent` を更新。`isSaving` フラグで手動保存と自動保存の競合を防止。
  - `autoSave()` を追加（500ms デバウンス）。blur 時に、新規ノート（`NOTE_ID === null`）や内容未変更の場合は保存をスキップする。
  - エディタごとの blur 配線: CodeMirror は `view.dom.addEventListener('blur', ..., true)`、Quill は `selection-change` で `range === null` のとき、textarea フォールバックは `blur` イベントで `autoSave()` を呼ぶ。

## 使い方
- 保存ボタン押下または `Cmd/Ctrl + S` で保存すると、「保存しました」が表示され約 2 秒でフェードアウトする。失敗時は赤字で理由が表示される。
- エディタ本文からフォーカスを外すと、変更があれば自動的に保存され「自動保存しました」がグレーで表示される。
- 新規ノート（まだ一度も保存していない状態）では blur による自動保存は行われない。保存ボタンで一度保存すると以降は自動保存が有効になる。

## 技術的な補足
- blur イベントはバブリングしないため、CodeMirror では `view.dom` に capture フェーズ（第3引数 `true`）で登録して内部要素の blur を捕捉する。
- Quill 2 では `selection-change` の `range === null` がフォーカスアウトを意味する。
- `autoSave()` は `window.autoSave` として公開し、非同期に読み込まれる CodeMirror / Quill の各スクリプトブロックから参照する（`window.autoSave?.()`）。
- `_lastSavedContent` は初期表示時の内容（textarea の value もしくは Quill コンテナの innerHTML）で初期化し、直前の保存内容と一致する場合は無駄な API 呼び出しを避ける。
- `isSaving` フラグにより、自動保存の実行中に手動保存（またはその逆）が重複実行されないようにしている。
