<?php

declare(strict_types=1);

namespace SQNote\Database;

class Migrator
{
    public function __construct(
        private \PDO $pdo,
        private string $migrationsDir,
    ) {}

    public function run(): void
    {
        $applied = $this->getAppliedVersions();
        $files   = glob($this->migrationsDir . '/*.sql') ?: [];

        usort($files, static fn($a, $b) => version_compare(
            basename($a, '.sql'),
            basename($b, '.sql')
        ));

        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $applied, true)) {
                continue;
            }

            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("マイグレーションファイルを読み込めませんでした: {$file}");
            }

            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sql);
                $this->pdo->prepare(
                    'INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)'
                )->execute([$version, time()]);
                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw new \RuntimeException("マイグレーション {$version} が失敗しました: " . $e->getMessage());
            }
        }

        if (!empty($files)) {
            $latest = basename(end($files), '.sql');
            $this->pdo->prepare("UPDATE meta SET value = ? WHERE key = 'schema_version'")
                ->execute([$latest]);
        }
    }

    private function getAppliedVersions(): array
    {
        // schema_migrations テーブルが存在しない場合（初回）は空配列を返す
        try {
            return $this->pdo
                ->query('SELECT version FROM schema_migrations')
                ->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }
}
