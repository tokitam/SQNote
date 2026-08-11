<?php

declare(strict_types=1);

namespace SQNote\Repository;

class TagRepository extends AbstractRepository
{
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT t.id, t.name, t.created_at,
                    COUNT(nt.note_id) AS note_count
             FROM tags t
             LEFT JOIN note_tags nt ON nt.tag_id = t.id
             LEFT JOIN notes n      ON n.id = nt.note_id AND n.is_deleted = 0
             GROUP BY t.id
             ORDER BY t.name'
        )->fetchAll();

        return array_map($this->format(...), $rows);
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, created_at FROM tags WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? $this->format($row) : null;
    }

    public function findOrCreate(string $name): string
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tags WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();

        if ($row) {
            return $row['id'];
        }

        $id = $this->newId();
        $this->pdo->prepare(
            'INSERT INTO tags (id, name, created_at) VALUES (?, ?, ?)'
        )->execute([$id, $name, $this->now()]);

        return $id;
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM tags WHERE id = ?')->execute([$id]);
    }

    public function noteCount(string $id): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM note_tags nt
             JOIN notes n ON n.id = nt.note_id AND n.is_deleted = 0
             WHERE nt.tag_id = ?'
        );
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    private function format(array $row): array
    {
        return [
            'id'         => $row['id'],
            'name'       => $row['name'],
            'note_count' => (int) ($row['note_count'] ?? 0),
            'created_at' => $this->toIso8601((int) $row['created_at']),
        ];
    }
}
