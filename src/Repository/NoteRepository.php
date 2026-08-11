<?php

declare(strict_types=1);

namespace SQNote\Repository;

class NoteRepository extends AbstractRepository
{
    private const SORT_ALLOW_LIST = ['updated_at', 'created_at', 'title'];

    public function findAll(array $options = []): array
    {
        $sort  = in_array($options['sort'] ?? '', self::SORT_ALLOW_LIST, true)
            ? $options['sort']
            : 'updated_at';
        $order = strtolower($options['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $limit  = min((int) ($options['limit'] ?? 20), 100);
        $offset = max((int) ($options['offset'] ?? 0), 0);

        $where  = ['n.is_deleted = 0'];
        $params = [];

        if (!empty($options['notebook_id'])) {
            $where[]  = 'n.notebook_id = :notebook_id';
            $params[':notebook_id'] = $options['notebook_id'];
        }

        if (!empty($options['tag'])) {
            $where[]  = 'EXISTS (
                SELECT 1 FROM note_tags nt
                JOIN tags t ON t.id = nt.tag_id
                WHERE nt.note_id = n.id AND t.name = :tag
            )';
            $params[':tag'] = $options['tag'];
        }

        if (!empty($options['q'])) {
            $where[]  = '(n.title LIKE :q OR n.content LIKE :q)';
            $params[':q'] = '%' . $options['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT n.id, n.title, n.notebook_id, nb.name AS notebook_name,
                       n.content_type, n.source_url, n.created_at, n.updated_at,
                       SUBSTR(n.content, 1, 200) AS excerpt
                FROM notes n
                LEFT JOIN notebooks nb ON nb.id = n.notebook_id
                WHERE {$whereSql}
                ORDER BY n.{$sort} {$order}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $notes   = $stmt->fetchAll();
        $noteIds = array_column($notes, 'id');
        $tagMap  = $this->fetchTagMap($noteIds);
        $attMap  = $this->fetchAttachmentFlagMap($noteIds);

        return array_map(function ($row) use ($tagMap, $attMap) {
            return $this->formatList($row, $tagMap, $attMap);
        }, $notes);
    }

    public function count(array $options = []): int
    {
        $where  = ['n.is_deleted = 0'];
        $params = [];

        if (!empty($options['notebook_id'])) {
            $where[]  = 'n.notebook_id = :notebook_id';
            $params[':notebook_id'] = $options['notebook_id'];
        }

        if (!empty($options['tag'])) {
            $where[]  = 'EXISTS (
                SELECT 1 FROM note_tags nt
                JOIN tags t ON t.id = nt.tag_id
                WHERE nt.note_id = n.id AND t.name = :tag
            )';
            $params[':tag'] = $options['tag'];
        }

        if (!empty($options['q'])) {
            $where[]  = '(n.title LIKE :q OR n.content LIKE :q)';
            $params[':q'] = '%' . $options['q'] . '%';
        }

        $whereSql = implode(' AND ', $where);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notes n WHERE {$whereSql}");
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.title, n.content, n.content_type, n.notebook_id,
                    nb.name AS notebook_name, n.source_url, n.created_at, n.updated_at
             FROM notes n
             LEFT JOIN notebooks nb ON nb.id = n.notebook_id
             WHERE n.id = ? AND n.is_deleted = 0'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $tagMap = $this->fetchTagMap([$id]);
        $attMap = $this->fetchAttachmentMetaMap([$id]);

        return array_merge($this->formatBase($row), [
            'content'     => $row['content'],
            'tags'        => $tagMap[$id] ?? [],
            'attachments' => $attMap[$id] ?? [],
        ]);
    }

    public function create(array $data): string
    {
        $id  = $this->newId();
        $now = $data['created_at'] ?? $this->now();
        $upd = $data['updated_at'] ?? $now;

        $this->pdo->prepare(
            'INSERT INTO notes (id, notebook_id, title, content, content_type, source_url, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $id,
            $data['notebook_id'] ?? null,
            $data['title'] ?? '',
            $data['content'] ?? '',
            $data['content_type'] ?? 'markdown',
            $data['source_url'] ?? null,
            $now,
            $upd,
        ]);

        return $id;
    }

    public function update(string $id, array $data): void
    {
        $fields = [];
        $params = [];

        foreach (['title', 'content', 'content_type', 'notebook_id', 'source_url'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[]  = $data[$field];
            }
        }

        if (empty($fields)) {
            return;
        }

        $fields[] = 'updated_at = ?';
        $params[] = $this->now();
        $params[] = $id;

        $this->pdo->prepare(
            'UPDATE notes SET ' . implode(', ', $fields) . ' WHERE id = ?'
        )->execute($params);
    }

    public function softDelete(string $id): void
    {
        $now = $this->now();
        $this->pdo->prepare(
            'UPDATE notes SET is_deleted = 1, deleted_at = ?, updated_at = ? WHERE id = ?'
        )->execute([$now, $now, $id]);
    }

    public function attachTag(string $noteId, string $tagId): void
    {
        $this->pdo->prepare(
            'INSERT OR IGNORE INTO note_tags (note_id, tag_id) VALUES (?, ?)'
        )->execute([$noteId, $tagId]);
    }

    public function detachAllTags(string $noteId): void
    {
        $this->pdo->prepare('DELETE FROM note_tags WHERE note_id = ?')->execute([$noteId]);
    }

    private function fetchTagMap(array $noteIds): array
    {
        if (empty($noteIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($noteIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT nt.note_id, t.name FROM note_tags nt
             JOIN tags t ON t.id = nt.tag_id
             WHERE nt.note_id IN ({$placeholders})
             ORDER BY t.name"
        );
        $stmt->execute($noteIds);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['note_id']][] = $row['name'];
        }
        return $map;
    }

    private function fetchAttachmentFlagMap(array $noteIds): array
    {
        if (empty($noteIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($noteIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT note_id FROM attachments WHERE note_id IN ({$placeholders})"
        );
        $stmt->execute($noteIds);
        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $noteId) {
            $map[$noteId] = true;
        }
        return $map;
    }

    private function fetchAttachmentMetaMap(array $noteIds): array
    {
        if (empty($noteIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($noteIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, note_id, filename, mime_type, file_size, created_at
             FROM attachments WHERE note_id IN ({$placeholders}) ORDER BY created_at"
        );
        $stmt->execute($noteIds);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['note_id']][] = [
                'id'        => $row['id'],
                'filename'  => $row['filename'],
                'mime_type' => $row['mime_type'],
                'file_size' => (int) $row['file_size'],
                'url'       => '/api/v1/attachments/' . $row['id'],
            ];
        }
        return $map;
    }

    private function formatBase(array $row): array
    {
        return [
            'id'            => $row['id'],
            'title'         => $row['title'],
            'content_type'  => $row['content_type'],
            'notebook_id'   => $row['notebook_id'],
            'notebook_name' => $row['notebook_name'],
            'source_url'    => $row['source_url'],
            'created_at'    => $this->toIso8601((int) $row['created_at']),
            'updated_at'    => $this->toIso8601((int) $row['updated_at']),
        ];
    }

    private function formatList(array $row, array $tagMap, array $attMap): array
    {
        return array_merge($this->formatBase($row), [
            'excerpt'         => $row['excerpt'] ?? '',
            'tags'            => $tagMap[$row['id']] ?? [],
            'has_attachments' => isset($attMap[$row['id']]),
        ]);
    }
}
