<?php

declare(strict_types=1);

namespace SQNote\Repository;

abstract class AbstractRepository
{
    public function __construct(protected \PDO $pdo) {}

    protected function toIso8601(?int $ts): ?string
    {
        return $ts !== null ? gmdate('Y-m-d\TH:i:s\Z', $ts) : null;
    }

    protected function newId(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    protected function now(): int
    {
        return time();
    }
}
