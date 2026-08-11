<?php

declare(strict_types=1);

namespace SQNote\Api\Controller;

use SQNote\Repository\NoteRepository;
use SQNote\Service\NoteService;

class NoteController extends AbstractController
{
    public function __construct(
        private NoteRepository $notes,
        private NoteService $noteService,
    ) {}

    public function index(): never
    {
        $opts = [
            'notebook_id' => $_GET['notebook_id'] ?? null,
            'tag'         => $_GET['tag'] ?? null,
            'q'           => $_GET['q'] ?? null,
            'limit'       => (int) ($_GET['limit'] ?? 20),
            'offset'      => (int) ($_GET['offset'] ?? 0),
            'sort'        => $_GET['sort'] ?? 'updated_at',
            'order'       => $_GET['order'] ?? 'desc',
        ];
        $list  = $this->notes->findAll($opts);
        $total = $this->notes->count($opts);
        $this->jsonList($list, $total, $opts['limit'], $opts['offset']);
    }

    public function show(array $params): never
    {
        $note = $this->notes->findById($params['id']);
        if (!$note) {
            $this->notFound('Note');
        }
        $this->json($note);
    }

    public function create(): never
    {
        $body = $this->body();
        try {
            $id = $this->noteService->create([
                'title'        => $body['title'] ?? '',
                'content'      => $body['content'] ?? '',
                'content_type' => $body['content_type'] ?? 'markdown',
                'notebook_id'  => $body['notebook_id'] ?? null,
                'tags'         => $body['tags'] ?? [],
                'source_url'   => $body['source_url'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->error('INTERNAL_ERROR', $e->getMessage(), 500);
        }
        $note = $this->notes->findById($id);
        $this->json($note, 201);
    }

    public function update(array $params): never
    {
        $note = $this->notes->findById($params['id']);
        if (!$note) {
            $this->notFound('Note');
        }
        $body = $this->body();
        try {
            $this->noteService->update($params['id'], $body);
        } catch (\Throwable $e) {
            $this->error('INTERNAL_ERROR', $e->getMessage(), 500);
        }
        $this->json($this->notes->findById($params['id']));
    }

    public function delete(array $params): never
    {
        $note = $this->notes->findById($params['id']);
        if (!$note) {
            $this->notFound('Note');
        }
        $this->noteService->delete($params['id']);
        $this->json(null);
    }

    public function createAttachment(array $params): never
    {
        $note = $this->notes->findById($params['note_id']);
        if (!$note) {
            $this->notFound('Note');
        }

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->error('INVALID_PARAM', 'ファイルのアップロードに失敗しました');
        }

        $binary   = file_get_contents($file['tmp_name']);
        $filename = $file['name'];
        $mime     = $file['type'] ?: 'application/octet-stream';

        try {
            $attId = $this->noteService->addAttachment($params['note_id'], $filename, $mime, $binary);
        } catch (\Throwable $e) {
            $this->error('INTERNAL_ERROR', $e->getMessage(), 500);
        }

        $this->json(['id' => $attId, 'filename' => $filename, 'mime_type' => $mime,
                     'url' => '/api/v1/attachments/' . $attId], 201);
    }
}
