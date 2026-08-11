<?php

declare(strict_types=1);

namespace SQNote\Repository;

class NotebookRepository extends AbstractRepository
{
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT nb.id, nb.name, nb.created_at, nb.updated_at,
                    COUNT(n.id) AS note_count
             FROM notebooks nb
             LEFT JOIN notes n ON n.notebook_id = nb.id AND n.is_deleted = 0
             WHERE nb.is_deleted = 0
             GROUP BY nb.id
             ORDER BY nb.name'
        )->fetchAll();

        return array_map($this->format(...), $rows);
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT nb.id, nb.name, nb.created_at, nb.updated_at,
                    COUNT(n.id) AS note_count
             FROM notebooks nb
             LEFT JOIN notes n ON n.notebook_id = nb.id AND n.is_deleted = 0
             WHERE nb.id = ? AND nb.is_deleted = 0
             GROUP BY nb.id'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? $this->format($row) : null;
    }

    public function create(array $data): string
    {
        $id  = $this->newId();
        $now = $this->now();
        $this->pdo->prepare(
            'INSERT INTO notebooks (id, name, created_at, updated_at) VALUES (?, ?, ?, ?)'
        )->execute([$id, $data['name'], $now, $now]);

        return $id;
    }

    public function update(string $id, array $data): void
    {
        $this->pdo->prepare(
            'UPDATE notebooks SET name = ?, updated_at = ? WHERE id = ?'
        )->execute([$data['name'], $this->now(), $id]);
    }

    public function softDelete(string $id): void
    {
        $now = $this->now();
        $this->pdo->prepare(
            'UPDATE notebooks SET is_deleted = 1, deleted_at = ?, updated_at = ? WHERE id = ?'
        )->execute([$now, $now, $id]);
    }

    public function noteCount(string $id): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM notes WHERE notebook_id = ? AND is_deleted = 0'
        );
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    private function format(array $row): array
    {
        return [
            'id'         => $row['id'],
            'name'       => $row['name'],
            'note_count' => (int) $row['note_count'],
            'created_at' => $this->toIso8601((int) $row['created_at']),
            'updated_at' => $this->toIso8601((int) $row['updated_at']),
        ];
    }
}
