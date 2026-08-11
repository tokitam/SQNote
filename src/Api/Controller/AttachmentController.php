<?php

declare(strict_types=1);

namespace SQNote\Api\Controller;

use SQNote\Repository\AttachmentRepository;
use SQNote\Service\NoteService;

class AttachmentController extends AbstractController
{
    public function __construct(
        private AttachmentRepository $attachments,
        private NoteService $noteService,
    ) {}

    public function show(array $params): never
    {
        $att = $this->attachments->findById($params['id']);
        if (!$att) {
            $this->notFound('Attachment');
        }
        header('Content-Type: ' . $att['mime_type']);
        header('Content-Disposition: inline; filename="' . addslashes($att['filename']) . '"');
        header('Content-Length: ' . $att['file_size']);
        echo $att['data'];
        exit;
    }

    public function delete(array $params): never
    {
        $att = $this->attachments->findById($params['id']);
        if (!$att) {
            $this->notFound('Attachment');
        }
        $this->noteService->deleteAttachment($params['id']);
        $this->json(null);
    }
}
