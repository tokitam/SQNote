# Evernote .enex インポート仕様

## 概要

Evernote からエクスポートした `.enex` ファイルを SQNote データベースにインポートする。
インポートはコマンドラインから実行する。

## enex ファイル形式

`.enex` は XML 形式のファイル。Evernote ノートのエクスポートフォーマット。

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE en-export SYSTEM "http://xml.evernote.com/pub/evernote-export4.dtd">
<en-export export-date="20260811T000000Z" application="Evernote" version="10.x">
  <note>
    <title>ノートタイトル</title>
    <content><![CDATA[<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">
<en-note>本文 HTML</en-note>
    ]]></content>
    <created>20260101T000000Z</created>
    <updated>20260811T000000Z</updated>
    <tag>タグ名</tag>
    <note-attributes>
      <source-url>https://example.com</source-url>
    </note-attributes>
    <resource>
      <data encoding="base64">base64データ</data>
      <mime>image/png</mime>
      <resource-attributes>
        <file-name>image.png</file-name>
      </resource-attributes>
    </resource>
  </note>
</en-export>
```

## CLI コマンド仕様

```
php sqnote import:enex [options] <enex-file>
```

### 引数

| 引数 | 説明 |
|---|---|
| `<enex-file>` | インポートする .enex ファイルのパス（必須） |

### オプション

| オプション | デフォルト | 説明 |
|---|---|---|
| `--db` | 設定ファイルの DB パス | 対象 .sqnote ファイルのパス |
| `--notebook` | enex ファイル名 | インポート先ノートブック名 |
| `--dry-run` | false | 実際には書き込まずに処理内容を表示 |
| `--skip-duplicates` | false | 同一タイトル・作成日時のノートをスキップ |
| `--verbose` | false | 詳細ログを出力 |

### 使用例

```bash
# 基本的なインポート
php sqnote import:enex ~/Desktop/MyNotes.enex

# インポート先ノートブックを指定
php sqnote import:enex --notebook="Evernote移行" ~/Desktop/MyNotes.enex

# ドライラン（書き込みなし）
php sqnote import:enex --dry-run ~/Desktop/MyNotes.enex

# 特定の DB ファイルを指定
php sqnote import:enex --db=/path/to/notes.sqnote ~/Desktop/MyNotes.enex
```

## インポート処理フロー

```
1. .enex ファイルの読み込み・XML パース
2. 対象 .sqnote ファイルをオープン（存在しない場合は新規作成）
3. インポート先ノートブックの取得または作成
4. ノートごとに処理:
   a. タイトル・日時・タグ・ソースURLを抽出
   b. ENML（Evernote XML）を HTML サブセットに変換
   c. 添付ファイル（resource）を抽出し attachments テーブルに格納
   d. 本文中の <en-media> タグを SQNote の img タグに変換
   e. notes テーブルにインポート
   f. note_tags テーブルにタグを関連付け
5. 処理結果のサマリーを表示
```

## ENML → HTML 変換ルール

Evernote のノート本文は ENML（Evernote Markup Language）形式。SQNote の HTML サブセットに変換する。

| ENML | SQNote HTML |
|---|---|
| `<en-note>` | `<div>` |
| `<en-media type="image/*" hash="...">` | `<img src="attachment://{id}" data-sqnote-attachment-id="{id}" alt="{filename}">` |
| `<en-media type="application/*" hash="...">` | `<a href="/api/v1/attachments/{id}">{filename}</a>` |
| `<en-todo checked="false">` | `<input type="checkbox" disabled>` |
| `<en-todo checked="true">` | `<input type="checkbox" disabled checked>` |
| その他のタグ | そのまま保持（許可タグのみ） |

ENML の `hash` 属性は MD5 ハッシュ。対応する `<resource>` 要素を特定するために使用し、インポート後は `attachments.id`（UUID）に置き換える。

インポートされたノートの `content_type` は `html` で保存する。
Evernote の本文は HTML ベースの ENML であり、Markdown への自動変換は情報ロスのリスクがあるためそのまま保持する。

## 重複チェック

`--skip-duplicates` 指定時は以下の条件で重複判定する。

- 同一ノートブック内に同じ `title` かつ同じ `created_at` のノートが存在する場合はスキップ

## 出力例

```
SQNote - Evernote .enex Importer
==================================
File     : MyNotes.enex
Notebook : Evernote移行
DB       : /path/to/notes.sqnote

Processing...

  [OK] ノートタイトル1 (3 attachments)
  [OK] ノートタイトル2
  [SKIP] 重複ノート (--skip-duplicates)
  [WARN] ノートタイトル3: 不明な ENML タグ <custom-tag> をスキップしました

==================================
Results:
  Total   : 10
  Imported: 8
  Skipped : 1
  Errors  : 1
```

## エラーハンドリング

| エラー種別 | 挙動 |
|---|---|
| XML パースエラー | 処理を中断し、エラーメッセージを表示 |
| 不明な ENML タグ | 警告を表示してタグを除去し、処理を継続 |
| 添付ファイルの Base64 デコードエラー | 警告を表示してスキップし、ノート本体はインポート |
| DB 書き込みエラー | ロールバックして中断 |

すべてのノートを1トランザクションでインポートする。途中でエラーが発生した場合はロールバックし、インポート済みデータが中途半端な状態にならないようにする。
