<?php

declare(strict_types=1);

namespace SQNote\Database;

class Connection
{
    private \PDO $pdo;

    public function __construct(string $path, string $passphrase)
    {
        if ($passphrase === '') {
            throw new \RuntimeException(
                'SQNOTE_DB_PASS が設定されていません。.env ファイルで SQNOTE_DB_PASS を設定してください。'
            );
        }

        $isNew = !file_exists($path);

        // ディレクトリが存在しない場合は作成する
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $this->pdo = new \PDO('sqlite:' . $path);
        } catch (\PDOException $e) {
            throw new \RuntimeException('データベースファイルを開けませんでした: ' . $e->getMessage());
        }

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        // SQLCipher: パスフレーズ設定（最初のクエリ実行前に必ず行う）
        $this->pdo->exec('PRAGMA key = ' . $this->pdo->quote($passphrase));

        // WAL モードを有効化（バックアップ中の整合性向上）
        $this->pdo->exec('PRAGMA journal_mode = WAL');

        // 外部キー制約の有効化
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        if ($isNew) {
            $this->initMeta();
        } else {
            $this->verifyAppName();
        }
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    private function initMeta(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS meta (
                key   TEXT NOT NULL PRIMARY KEY,
                value TEXT NOT NULL
            )
        ');

        $stmt = $this->pdo->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
        foreach ([
            ['app_name',       'SQNote'],
            ['schema_version', '1.0.0'],
            ['created_at',     (string) time()],
        ] as [$k, $v]) {
            $stmt->execute([$k, $v]);
        }
    }

    private function verifyAppName(): void
    {
        try {
            $row = $this->pdo
                ->query("SELECT value FROM meta WHERE key = 'app_name'")
                ->fetch();
        } catch (\PDOException) {
            // meta テーブルが存在しない場合（SQLCipher 未対応 DB 等）
            throw new \RuntimeException(
                '指定されたファイルは SQNote データベースではありません。' .
                'パスフレーズが正しくない可能性もあります。'
            );
        }

        if (!$row || $row['value'] !== 'SQNote') {
            throw new \RuntimeException(
                '指定されたファイルは SQNote データベースではありません。'
            );
        }
    }
}
