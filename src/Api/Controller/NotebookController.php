<?php

declare(strict_types=1);

namespace SQNote\Api\Controller;

use SQNote\Repository\NotebookRepository;
use SQNote\Service\NotebookService;

class NotebookController extends AbstractController
{
    public function __construct(
        private NotebookRepository $notebooks,
        private NotebookService $notebookService,
    ) {}

    public function index(): never
    {
        $this->json($this->notebooks->findAll());
    }

    public function create(): never
    {
        $body = $this->body();
        if (empty($body['name'])) {
            $this->error('INVALID_PARAM', 'name は必須です');
        }
        $id = $this->notebookService->create(['name' => $body['name']]);
        $this->json($this->notebooks->findById($id), 201);
    }

    public function update(array $params): never
    {
        $nb = $this->notebooks->findById($params['id']);
        if (!$nb) {
            $this->notFound('Notebook');
        }
        $body = $this->body();
        if (empty($body['name'])) {
            $this->error('INVALID_PARAM', 'name は必須です');
        }
        $this->notebookService->update($params['id'], ['name' => $body['name']]);
        $this->json($this->notebooks->findById($params['id']));
    }

    public function delete(array $params): never
    {
        $nb = $this->notebooks->findById($params['id']);
        if (!$nb) {
            $this->notFound('Notebook');
        }
        try {
            $this->notebookService->delete($params['id']);
        } catch (\RuntimeException $e) {
            $this->error('CONFLICT', $e->getMessage(), 409);
        }
        $this->json(null);
    }
}
