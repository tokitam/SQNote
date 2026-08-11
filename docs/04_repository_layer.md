# Repository 層実装

## 概要

DB アクセスを担う 4 つの Repository クラスを実装した。すべてプリペアドステートメントを使用し、呼び出し元には整形済み配列を返す。

## 実装内容

- `src/Repository/AbstractRepository.php` — 基底クラス（UUID 生成・日時変換）
- `src/Repository/NoteRepository.php` — ノートの CRUD・タグ関連付け・一覧検索
- `src/Repository/NotebookRepository.php` — ノートブックの CRUD・ノート件数取得
- `src/Repository/TagRepository.php` — タグの CRUD・findOrCreate
- `src/Repository/AttachmentRepository.php` — 添付ファイルの BLOB 保存・取得

## 技術的な補足

### N+1 対策

`NoteRepository::findAll()` ではノート一覧取得後に note_id の IN 句でタグと添付フラグをまとめて取得し、PHP 側でマッピングする。

### ソートのホワイトリスト

`NoteRepository::findAll()` の `sort` パラメータは `['updated_at', 'created_at', 'title']` のいずれかのみ許可。カラム名を SQL に直接埋め込むため、このホワイトリスト検証が SQL インジェクション対策になる。

### BLOB の保存

`AttachmentRepository::create()` では `PDO::PARAM_LOB` でバイナリを直接バインドする。`hash_sha256` と `file_size` は Repository 内で自動計算する。

### 添付ファイルの一覧と詳細

- `findByNoteId()` はメタ情報のみ返す（バイナリは含めない）
- `findById()` はバイナリデータ本体も含めて返す（ダウンロード用）

### インポート時の日時指定

`NoteRepository::create()` は `created_at`・`updated_at` を `$data` で渡せる。省略時は `time()` を使用。enex インポート時に元の作成日時を引き継ぐために使用する。
