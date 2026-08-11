<?php

declare(strict_types=1);

namespace SQNote\Service;

use SQNote\Repository\NotebookRepository;

class NotebookService
{
    public function __construct(
        private NotebookRepository $notebooks,
    ) {}

    public function create(array $data): string
    {
        return $this->notebooks->create(['name' => $data['name']]);
    }

    public function update(string $id, array $data): void
    {
        $this->notebooks->update($id, ['name' => $data['name']]);
    }

    public function delete(string $id): void
    {
        if ($this->notebooks->noteCount($id) > 0) {
            throw new \RuntimeException('ノートが存在するノートブックは削除できません。');
        }
        $this->notebooks->softDelete($id);
    }
}
