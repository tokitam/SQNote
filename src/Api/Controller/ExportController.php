<?php

declare(strict_types=1);

namespace SQNote\Api\Controller;

use SQNote\Service\ExportService;

class ExportController extends AbstractController
{
    public function __construct(private ExportService $export) {}

    public function json(): never
    {
        $notebookId = $_GET['notebook_id'] ?? null;
        $noteId     = $_GET['note_id'] ?? null;
        $json       = $this->export->toJson($notebookId, $noteId);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="sqnote_export_' . date('Ymd_His') . '.json"');
        echo $json;
        exit;
    }

    public function markdown(): never
    {
        $notebookId = $_GET['notebook_id'] ?? null;
        $zip        = $this->export->toMarkdownZip($notebookId);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="sqnote_export_' . date('Ymd_His') . '.zip"');
        header('Content-Length: ' . strlen($zip));
        echo $zip;
        exit;
    }
}
