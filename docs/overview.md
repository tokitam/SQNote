# SQNote 概要

## コンセプト

SQLite データベースをメインDBとして使用するノートアプリ。
データファイルを SQLCipher で暗号化し、コピーするだけでバックアップ・リストアができる。

**データフォーマットおよび入出力仕様の定義こそが SQNote の本丸。**
バックエンドやフロントエンドは言語・フレームワーク問わず、この仕様に準拠することで実装できる。

## 背景

Evernote の使い勝手が低下しているため、データを移行できる自前ノートアプリを構築する。
最低限テキストデータだけでも移行できることを目標とする。

## 設計思想

- **ポータビリティ**: `.sqnote` ファイル（SQLCipher 暗号化 SQLite DB）を1ファイルコピーするだけでバックアップ・リストア完了
- **言語非依存**: データフォーマット仕様を公開し、PHP・TypeScript・Python・Ruby・Go など任意の言語で実装可能
- **フロントエンド非依存**: 現実装は PHP 製 Web UI だが、将来的に React / React Native へ移行可能な設計
- **シンプルな認証**: 本実装ではユーザー認証なし、BASIC 認証で画面を保護

## リポジトリ

- GitHub: https://github.com/tokitam/SQNote
- 管理者: tokitam

## ドキュメント構成

| ファイル | 内容 |
|---|---|
| [overview.md](./overview.md) | 本ファイル。プロジェクト概要 |
| [data-format.md](./data-format.md) | **データフォーマット仕様**（SQNote の核心） |
| [database-schema.md](./database-schema.md) | SQLite スキーマ定義 |
| [api-spec.md](./api-spec.md) | REST API 仕様 |
| [frontend.md](./frontend.md) | フロントエンド設計 |
| [import-enex.md](./import-enex.md) | Evernote .enex インポート仕様 |
| [architecture.md](./architecture.md) | システムアーキテクチャ（PHP 実装） |

## 現行実装スコープ（v1）

| 領域 | 技術 |
|---|---|
| バックエンド | PHP |
| フロントエンド | PHP（サーバーサイドレンダリング） |
| データベース | SQLite + SQLCipher |
| 認証 | BASIC 認証 |
| インポート | Evernote .enex（CLI） |

## 将来的な拡張

- バックエンド: TypeScript (Node.js) / Python / Ruby / Go による再実装
- フロントエンド: React / React Native
- ユーザー認証: JWT / OAuth
- クラウド同期: S3 互換ストレージへの自動バックアップ
