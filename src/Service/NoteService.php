<?php

declare(strict_types=1);

namespace SQNote\Service;

use SQNote\Repository\AttachmentRepository;
use SQNote\Repository\NoteRepository;
use SQNote\Repository\TagRepository;

class NoteService
{
    public function __construct(
        private \PDO $pdo,
        private NoteRepository $notes,
        private TagRepository $tags,
        private AttachmentRepository $attachments,
    ) {}

    public function create(array $data): string
    {
        // 既に外側のトランザクション内（一括インポート等）で呼ばれる場合は
        // それに参加し、自分でネストした beginTransaction はしない（PDO は非対応）。
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $noteId = $this->notes->create([
                'title'        => $data['title'] ?? '',
                'content'      => $data['content'] ?? '',
                'content_type' => $data['content_type'] ?? 'markdown',
                'notebook_id'  => $data['notebook_id'] ?? null,
                'source_url'   => $data['source_url'] ?? null,
                'created_at'   => $data['created_at'] ?? null,
                'updated_at'   => $data['updated_at'] ?? null,
            ]);

            foreach ($data['tags'] ?? [] as $tagName) {
                if (trim($tagName) === '') {
                    continue;
                }
                $tagId = $this->tags->findOrCreate(trim($tagName));
                $this->notes->attachTag($noteId, $tagId);
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $noteId;
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function update(string $id, array $data): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $updateFields = array_intersect_key($data, array_flip([
                'title', 'content', 'content_type', 'notebook_id', 'source_url',
            ]));
            if (!empty($updateFields)) {
                $this->notes->update($id, $updateFields);
            }

            if (array_key_exists('tags', $data)) {
                $this->notes->detachAllTags($id);
                foreach ($data['tags'] as $tagName) {
                    if (trim($tagName) === '') {
                        continue;
                    }
                    $tagId = $this->tags->findOrCreate(trim($tagName));
                    $this->notes->attachTag($id, $tagId);
                }
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(string $id): void
    {
        $this->notes->softDelete($id);
    }

    public function addAttachment(string $noteId, string $filename, string $mimeType, string $binaryData): string
    {
        return $this->attachments->create([
            'note_id'   => $noteId,
            'filename'  => $filename,
            'mime_type' => $mimeType,
            'data'      => $binaryData,
        ]);
    }

    public function deleteAttachment(string $attachmentId): void
    {
        $this->attachments->delete($attachmentId);
    }
}
