<?php

declare(strict_types=1);

namespace SQNote\Repository;

class NoteHistoryRepository extends AbstractRepository
{
    private const MAX_VERSIONS = 50;

    public function save(string $noteId, array $noteData): void
    {
        $version = $this->nextVersion($noteId);

        $this->pdo->prepare(
            'INSERT INTO note_history (id, note_id, title, content, content_type, version, saved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $this->newId(),
            $noteId,
            $noteData['title'] ?? '',
            $noteData['content'] ?? '',
            $noteData['content_type'] ?? 'markdown',
            $version,
            $this->now(),
        ]);

        $this->pruneOldVersions($noteId);
    }

    public function findByNoteId(string $noteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, note_id, title, version, saved_at
             FROM note_history WHERE note_id = ? ORDER BY version DESC'
        );
        $stmt->execute([$noteId]);

        return array_map(function ($row) {
            return [
                'id'       => $row['id'],
                'note_id'  => $row['note_id'],
                'title'    => $row['title'],
                'version'  => (int) $row['version'],
                'saved_at' => $this->toIso8601((int) $row['saved_at']),
            ];
        }, $stmt->fetchAll());
    }

    public function findById(string $historyId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, note_id, title, content, content_type, version, saved_at
             FROM note_history WHERE id = ?'
        );
        $stmt->execute([$historyId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return [
            'id'           => $row['id'],
            'note_id'      => $row['note_id'],
            'title'        => $row['title'],
            'content'      => $row['content'],
            'content_type' => $row['content_type'],
            'version'      => (int) $row['version'],
            'saved_at'     => $this->toIso8601((int) $row['saved_at']),
        ];
    }

    private function nextVersion(string $noteId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(version), 0) FROM note_history WHERE note_id = ?'
        );
        $stmt->execute([$noteId]);
        return (int) $stmt->fetchColumn() + 1;
    }

    private function pruneOldVersions(string $noteId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM note_history WHERE note_id = ? ORDER BY version DESC LIMIT -1 OFFSET ?'
        );
        $stmt->execute([$noteId, self::MAX_VERSIONS]);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->pdo->prepare("DELETE FROM note_history WHERE id IN ({$placeholders})")->execute($ids);
    }
}
