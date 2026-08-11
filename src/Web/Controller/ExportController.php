<?php

declare(strict_types=1);

namespace SQNote\Web\Controller;

use SQNote\Repository\NotebookRepository;
use SQNote\Repository\TagRepository;
use SQNote\Service\ExportService;

class ExportController extends AbstractController
{
    public function __construct(
        \Twig\Environment $twig,
        private NotebookRepository $notebookRepository,
        private TagRepository $tagRepository,
        private ExportService $exportService,
        private array $config,
    ) {
        parent::__construct($twig);
    }

    public function index(): never
    {
        $this->render('export/index.html.twig', [
            'notebooks' => $this->notebookRepository->findAll(),
            'tags'      => $this->tagRepository->findAll(),
        ]);
    }

    public function downloadJson(): never
    {
        $notebookId = $_GET['notebook_id'] ?? null;

        $options = [];
        if ($notebookId) {
            $options['notebook_id'] = $notebookId;
        }

        $json     = $this->exportService->toJson($options);
        $filename = 'sqnote_export_' . date('Ymd_His') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $json;
        exit;
    }

    public function downloadMarkdown(): never
    {
        $zip      = $this->exportService->toMarkdownZip();
        $filename = 'sqnote_markdown_' . date('Ymd_His') . '.zip';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $zip;
        exit;
    }

    public function downloadBackup(): never
    {
        $path = $this->config['db']['path'];

        if (!file_exists($path)) {
            http_response_code(404);
            echo 'データベースファイルが見つかりません。';
            exit;
        }

        $filename = 'sqnote_backup_' . date('Ymd_His') . '.sqnote';

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
