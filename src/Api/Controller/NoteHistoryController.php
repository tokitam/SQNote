<?php

declare(strict_types=1);

namespace SQNote\Api\Controller;

use SQNote\Repository\NoteHistoryRepository;
use SQNote\Repository\NoteRepository;
use SQNote\Service\NoteService;

class NoteHistoryController extends AbstractController
{
    public function __construct(
        private NoteRepository $notes,
        private NoteHistoryRepository $noteHistory,
        private NoteService $noteService,
    ) {}

    public function index(array $params): never
    {
        $note = $this->notes->findById($params['id']);
        if (!$note) {
            $this->notFound('Note');
        }

        $list = $this->noteHistory->findByNoteId($params['id']);
        $this->json($list);
    }

    public function show(array $params): never
    {
        $note = $this->notes->findById($params['id']);
        if (!$note) {
            $this->notFound('Note');
        }

        $history = $this->noteHistory->findById($params['hid']);
        if (!$history || $history['note_id'] !== $params['id']) {
            $this->notFound('NoteHistory');
        }

        $this->json($history);
    }

    public function restore(array $params): never
    {
        $note = $this->notes->findById($params['id']);
        if (!$note) {
            $this->notFound('Note');
        }

        $history = $this->noteHistory->findById($params['hid']);
        if (!$history || $history['note_id'] !== $params['id']) {
            $this->notFound('NoteHistory');
        }

        try {
            $this->noteService->update($params['id'], [
                'title'        => $history['title'],
                'content'      => $history['content'],
                'content_type' => $history['content_type'],
            ]);
        } catch (\Throwable $e) {
            $this->error('INTERNAL_ERROR', $e->getMessage(), 500);
        }

        $this->json($this->notes->findById($params['id']));
    }
}
