<?php

declare(strict_types=1);

namespace SQNote\Service;

use SQNote\Repository\AttachmentRepository;
use SQNote\Repository\NoteRepository;

class ExportService
{
    public function __construct(
        private NoteRepository $notes,
        private AttachmentRepository $attachments,
    ) {}

    public function toJson(?string $notebookId = null, ?string $noteId = null): string
    {
        $options = [];
        if ($notebookId !== null) {
            $options['notebook_id'] = $notebookId;
        }

        if ($noteId !== null) {
            $note = $this->notes->findById($noteId);
            $notesList = $note ? [$note] : [];
        } else {
            $notesList = $this->notes->findAll(array_merge($options, ['limit' => 10000]));
            // 詳細（本文・添付）も含める
            $notesList = array_map(fn($n) => $this->notes->findById($n['id']), $notesList);
        }

        $payload = [
            'sqnote_version' => '1.0.0',
            'exported_at'    => gmdate('Y-m-d\TH:i:s\Z'),
            'notes'          => array_values(array_filter(array_map(
                fn($note) => $note ? $this->buildNoteJson($note) : null,
                $notesList
            ))),
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function toMarkdownZip(?string $notebookId = null): string
    {
        $options = $notebookId ? ['notebook_id' => $notebookId] : [];
        $notesList = $this->notes->findAll(array_merge($options, ['limit' => 10000]));

        $zip     = new \ZipArchive();
        $tmpFile = tempnam(sys_get_temp_dir(), 'sqnote_export_');
        $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($notesList as $noteSum) {
            $note = $this->notes->findById($noteSum['id']);
            if (!$note) {
                continue;
            }

            $dir      = $this->sanitizeFilename($note['notebook_name'] ?? 'Inbox');
            $filename = $this->sanitizeFilename($note['title'] ?: $note['id']);
            $ext      = $note['content_type'] === 'markdown' ? 'md' : 'html';
            $path     = "{$dir}/{$filename}.{$ext}";

            $zip->addFromString($path, $note['content'] ?? '');
        }

        $zip->close();

        $binary = file_get_contents($tmpFile);
        unlink($tmpFile);

        return $binary ?: '';
    }

    private function buildNoteJson(array $note): array
    {
        $attachments = array_map(function ($att) {
            $full = $this->attachments->findById($att['id']);
            return [
                'id'           => $att['id'],
                'filename'     => $att['filename'],
                'mime_type'    => $att['mime_type'],
                'data_base64'  => $full ? base64_encode($full['data']) : '',
                'hash_sha256'  => $att['hash_sha256'] ?? '',
            ];
        }, $note['attachments'] ?? []);

        return [
            'id'           => $note['id'],
            'title'        => $note['title'],
            'content'      => $note['content'],
            'content_type' => $note['content_type'],
            'created_at'   => $note['created_at'],
            'updated_at'   => $note['updated_at'],
            'notebook'     => [
                'id'   => $note['notebook_id'],
                'name' => $note['notebook_name'],
            ],
            'tags'         => $note['tags'] ?? [],
            'source_url'   => $note['source_url'],
            'attachments'  => $attachments,
        ];
    }

    private function sanitizeFilename(string $name): string
    {
        return preg_replace('/[\/\\\:\*\?"<>\|]/', '_', $name) ?: 'untitled';
    }
}
