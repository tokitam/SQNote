<?php

declare(strict_types=1);

namespace SQNote\Cli;

use SQNote\Container;
use SQNote\Importer\EnexImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportCommand extends Command
{
    protected static $defaultName = 'import:enex';

    protected function configure(): void
    {
        $this
            ->setName('import:enex')
            ->setDescription('Evernote .enex ファイルを SQNote DB にインポートする')
            ->addArgument('file', InputArgument::REQUIRED, 'インポートする .enex ファイルのパス')
            ->addOption('db',              null, InputOption::VALUE_REQUIRED, 'DB ファイルパス（省略時は設定ファイルの値）')
            ->addOption('notebook',        null, InputOption::VALUE_REQUIRED, 'インポート先ノートブック名（省略時はファイル名）')
            ->addOption('dry-run',         null, InputOption::VALUE_NONE,     '実際には書き込まず処理内容を表示する')
            ->addOption('skip-duplicates', null, InputOption::VALUE_NONE,     '同一タイトル・作成日時のノートをスキップする')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $enexPath = $input->getArgument('file');
        $dryRun   = $input->getOption('dry-run');
        $skipDupe = $input->getOption('skip-duplicates');

        // 設定読み込み
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        if ($input->getOption('db')) {
            $config['db']['path'] = $input->getOption('db');
        }

        $io->title('SQNote - Evernote .enex Importer');
        $io->table([], [
            ['File',     $enexPath],
            ['DB',       $config['db']['path']],
            ['Dry-run',  $dryRun ? 'YES' : 'no'],
        ]);

        // コンテナ初期化
        try {
            $container = new Container($config);
        } catch (\Throwable $e) {
            $io->error('DB の初期化に失敗しました: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // インポート先ノートブックの取得または作成
        $notebookName = $input->getOption('notebook')
            ?: pathinfo($enexPath, PATHINFO_FILENAME);

        $notebookRepo = $container->notebookRepository();
        $notebooks    = $notebookRepo->findAll();
        $notebook     = null;
        foreach ($notebooks as $nb) {
            if ($nb['name'] === $notebookName) {
                $notebook = $nb;
                break;
            }
        }
        if (!$notebook) {
            $notebookId = $container->notebookService()->create(['name' => $notebookName]);
            $notebook   = $notebookRepo->findById($notebookId);
            $io->writeln("ノートブック「{$notebookName}」を作成しました。");
        }

        $io->writeln("インポート先ノートブック: <info>{$notebook['name']}</info>");
        $io->writeln('');
        $io->writeln('処理中...');

        // インポート実行
        $importer = new EnexImporter(
            $container->noteService(),
            $container->noteRepository(),
            $container->pdo(),
        );

        try {
            $results = $importer->import($enexPath, $notebook['id'], [
                'dry_run'         => $dryRun,
                'skip_duplicates' => $skipDupe,
            ]);
        } catch (\Throwable $e) {
            $io->error('インポート中にエラーが発生しました: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // 警告の表示
        foreach ($results['warnings'] as $warn) {
            $io->writeln("  <comment>[WARN]</comment> {$warn}");
        }

        // サマリー
        $io->writeln('');
        $io->section('結果');
        $io->table([], [
            ['合計',       $results['total']],
            ['インポート', $results['imported']],
            ['スキップ',   $results['skipped']],
            ['エラー',     $results['errors']],
        ]);

        if ($dryRun) {
            $io->note('ドライランのため DB への書き込みは行っていません。');
        }

        return $results['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
