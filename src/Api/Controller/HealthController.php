<?php

declare(strict_types=1);

namespace SQNote\Api\Controller;

class HealthController extends AbstractController
{
    public function __construct(private array $config, private \PDO $pdo) {}

    public function index(): never
    {
        $version = $this->pdo
            ->query("SELECT value FROM meta WHERE key = 'schema_version'")
            ->fetchColumn();

        $this->json([
            'app_name'       => 'SQNote',
            'schema_version' => $version ?: 'unknown',
            'db_path'        => $this->config['db']['path'],
        ]);
    }
}
