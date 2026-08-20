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

    private const ALLOW_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf',
        'text/plain', 'text/csv', 'text/markdown',
        'application/zip',
    ];

    /** ブラウザ・OS ごとに揺れる MIME を正規名へ寄せる */
    private const MIME_ALIASES = [
        'application/x-zip-compressed' => 'application/zip',
        'application/x-zip'            => 'application/zip',
        'application/x-compressed'     => 'application/zip',
    ];

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

        // ブラウザ申告値と、サーバ側で中身から判定した値の両方を候補にする。
        // ZIP は環境により application/x-zip-compressed 等になるため、実体判定で救済する。
        $normalize = static fn(string $m): string => self::MIME_ALIASES[$m] ?? $m;
        $claimed   = $normalize($file['type'] ?: 'application/octet-stream');
        $detected  = null;
        if (function_exists('finfo_open') && ($finfo = finfo_open(FILEINFO_MIME_TYPE))) {
            $detected = finfo_file($finfo, $file['tmp_name']) ?: null;
            finfo_close($finfo);
            if ($detected !== null) {
                $detected = $normalize($detected);
            }
        }

        $candidates = array_filter([$claimed, $detected]);
        $allowed    = array_values(array_intersect($candidates, self::ALLOW_MIMES));
        if (!$allowed) {
            $this->error('INVALID_PARAM', '許可されていないファイル形式です', 422);
        }
        $mime = $allowed[0];

        try {
            $attId = $this->noteService->addAttachment($params['note_id'], $filename, $mime, $binary);
        } catch (\Throwable $e) {
            $this->error('INTERNAL_ERROR', $e->getMessage(), 500);
        }

        $this->json(['id' => $attId, 'filename' => $filename, 'mime_type' => $mime,
                     'url' => '/api/v1/attachments/' . $attId], 201);
    }
}
