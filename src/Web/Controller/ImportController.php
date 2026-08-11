<?php

declare(strict_types=1);

namespace SQNote\Web\Controller;

use SQNote\Importer\EnexImporter;
use SQNote\Repository\NotebookRepository;
use SQNote\Repository\TagRepository;
use SQNote\Service\NotebookService;

class ImportController extends AbstractController
{
    public function __construct(
        \Twig\Environment $twig,
        private NotebookRepository $notebookRepository,
        private TagRepository $tagRepository,
        private NotebookService $notebookService,
        private EnexImporter $importer,
    ) {
        parent::__construct($twig);
    }

    public function index(): never
    {
        $this->render('import/index.html.twig', [
            'notebooks' => $this->notebookRepository->findAll(),
            'tags'      => $this->tagRepository->findAll(),
            'results'   => null,
            'error'     => null,
        ]);
    }

    public function store(): never
    {
        $notebooks = $this->notebookRepository->findAll();
        $tags      = $this->tagRepository->findAll();

        $file       = $_FILES['enex_file'] ?? null;
        $notebookId = $_POST['notebook_id'] ?? '';
        $newName    = trim($_POST['new_notebook_name'] ?? '');
        $skipDupes  = isset($_POST['skip_duplicates']);
        $dryRun     = isset($_POST['dry_run']);

        // ファイルバリデーション
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] === 0) {
            $this->render('import/index.html.twig', [
                'notebooks' => $notebooks,
                'tags'      => $tags,
                'results'   => null,
                'error'     => 'ファイルのアップロードに失敗しました。',
            ]);
        }

        if (!str_ends_with(strtolower($file['name']), '.enex')) {
            $this->render('import/index.html.twig', [
                'notebooks' => $notebooks,
                'tags'      => $tags,
                'results'   => null,
                'error'     => 'ファイル形式が正しくありません。.enex ファイルを選択してください。',
            ]);
        }

        // ノートブックの特定または作成
        if ($notebookId === 'new' || $notebookId === '') {
            if ($newName === '') {
                $newName = pathinfo($file['name'], PATHINFO_FILENAME);
            }
            $notebookId = $this->notebookService->create(['name' => $newName]);
            $notebooks  = $this->notebookRepository->findAll();
        }

        try {
            $results = $this->importer->import($file['tmp_name'], $notebookId, [
                'dry_run'         => $dryRun,
                'skip_duplicates' => $skipDupes,
            ]);
        } catch (\Throwable $e) {
            $this->render('import/index.html.twig', [
                'notebooks' => $notebooks,
                'tags'      => $tags,
                'results'   => null,
                'error'     => 'インポート中にエラーが発生しました: ' . $e->getMessage(),
            ]);
        }

        $this->render('import/index.html.twig', [
            'notebooks'   => $notebooks,
            'tags'        => $tags,
            'results'     => $results,
            'error'       => null,
            'dry_run'     => $dryRun,
            'notebook_id' => $notebookId,
        ]);
    }
}
