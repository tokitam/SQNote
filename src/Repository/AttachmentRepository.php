<?php

declare(strict_types=1);

namespace SQNote\Repository;

class AttachmentRepository extends AbstractRepository
{
    public function findByNoteId(string $noteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, note_id, filename, mime_type, hash_sha256, file_size, created_at
             FROM attachments WHERE note_id = ? ORDER BY created_at'
        );
        $stmt->execute([$noteId]);
        return array_map($this->formatMeta(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, note_id, filename, mime_type, data, hash_sha256, file_size, created_at
             FROM attachments WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? $this->formatFull($row) : null;
    }

    public function create(array $data): string
    {
        $id      = $this->newId();
        $binary  = $data['data'];
        $hash    = hash('sha256', $binary);
        $size    = strlen($binary);

        $stmt = $this->pdo->prepare(
            'INSERT INTO attachments (id, note_id, filename, mime_type, data, hash_sha256, file_size, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bindValue(1, $id);
        $stmt->bindValue(2, $data['note_id']);
        $stmt->bindValue(3, $data['filename']);
        $stmt->bindValue(4, $data['mime_type']);
        $stmt->bindValue(5, $binary, \PDO::PARAM_LOB);
        $stmt->bindValue(6, $hash);
        $stmt->bindValue(7, $size, \PDO::PARAM_INT);
        $stmt->bindValue(8, $this->now(), \PDO::PARAM_INT);
        $stmt->execute();

        return $id;
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM attachments WHERE id = ?')->execute([$id]);
    }

    private function formatMeta(array $row): array
    {
        return [
            'id'          => $row['id'],
            'note_id'     => $row['note_id'],
            'filename'    => $row['filename'],
            'mime_type'   => $row['mime_type'],
            'hash_sha256' => $row['hash_sha256'],
            'file_size'   => (int) $row['file_size'],
            'created_at'  => $this->toIso8601((int) $row['created_at']),
        ];
    }

    private function formatFull(array $row): array
    {
        return array_merge($this->formatMeta($row), ['data' => $row['data']]);
    }
}
