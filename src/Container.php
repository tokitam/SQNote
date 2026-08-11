<?php

declare(strict_types=1);

namespace SQNote;

use SQNote\Database\Connection;
use SQNote\Database\Migrator;
use SQNote\Repository\AttachmentRepository;
use SQNote\Repository\NoteRepository;
use SQNote\Repository\NotebookRepository;
use SQNote\Repository\TagRepository;
use SQNote\Service\ExportService;
use SQNote\Service\NoteService;
use SQNote\Service\NotebookService;

class Container
{
    private array $instances = [];
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function pdo(): \PDO
    {
        return $this->instances['pdo'] ??= (function () {
            $conn = new Connection(
                $this->config['db']['path'],
                $this->config['db']['passphrase']
            );
            $migrator = new Migrator(
                $conn->getPdo(),
                dirname(__DIR__) . '/migrations'
            );
            $migrator->run();
            return $conn->getPdo();
        })();
    }

    public function noteRepository(): NoteRepository
    {
        return $this->instances['noteRepo'] ??= new NoteRepository($this->pdo());
    }

    public function notebookRepository(): NotebookRepository
    {
        return $this->instances['notebookRepo'] ??= new NotebookRepository($this->pdo());
    }

    public function tagRepository(): TagRepository
    {
        return $this->instances['tagRepo'] ??= new TagRepository($this->pdo());
    }

    public function attachmentRepository(): AttachmentRepository
    {
        return $this->instances['attachmentRepo'] ??= new AttachmentRepository($this->pdo());
    }

    public function noteService(): NoteService
    {
        return $this->instances['noteService'] ??= new NoteService(
            $this->pdo(),
            $this->noteRepository(),
            $this->tagRepository(),
            $this->attachmentRepository(),
        );
    }

    public function notebookService(): NotebookService
    {
        return $this->instances['notebookService'] ??= new NotebookService(
            $this->notebookRepository(),
        );
    }

    public function exportService(): ExportService
    {
        return $this->instances['exportService'] ??= new ExportService(
            $this->noteRepository(),
            $this->attachmentRepository(),
        );
    }

    public function config(): array
    {
        return $this->config;
    }
}
