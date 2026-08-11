<?php

declare(strict_types=1);

namespace SQNote\Importer;

use SQNote\Repository\NoteRepository;
use SQNote\Service\NoteService;

class EnexImporter
{
    public function __construct(
        private NoteService $noteService,
        private NoteRepository $notes,
        private \PDO $pdo,
    ) {}

    /**
     * @param array{dry_run?: bool, skip_duplicates?: bool, verbose?: bool} $options
     * @return array{imported: int, skipped: int, errors: int, total: int, warnings: list<string>}
     */
    public function import(string $enexPath, string $notebookId, array $options = []): array
    {
        if (!file_exists($enexPath)) {
            throw new \InvalidArgumentException("ファイルが見つかりません: {$enexPath}");
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($enexPath);
        if ($xml === false) {
            $err = libxml_get_last_error();
            throw new \RuntimeException('enex ファイルのパースに失敗しました: ' . ($err ? $err->message : '不明なエラー'));
        }

        $results = ['imported' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0, 'warnings' => []];

        $this->pdo->beginTransaction();
        try {
            foreach ($xml->note as $noteEl) {
                $results['total']++;
                $this->processNote($noteEl, $notebookId, $options, $results);
            }
            if ($options['dry_run'] ?? false) {
                $this->pdo->rollBack();
            } else {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $results;
    }

    private function processNote(
        \SimpleXMLElement $el,
        string $notebookId,
        array $options,
        array &$results,
    ): void {
        $title     = (string) $el->title;
        $createdAt = $this->parseEnexDate((string) $el->created);
        $updatedAt = $this->parseEnexDate((string) $el->updated);

        if ($options['skip_duplicates'] ?? false) {
            if ($this->isDuplicate($title, $createdAt, $notebookId)) {
                $results['skipped']++;
                return;
            }
        }

        try {
            // リソース（添付ファイル）を hash → データのマップとして事前抽出
            $resourceMap = $this->extractResourceMap($el);

            $enml = (string) $el->content;
            [$html, $warnings] = $this->enmlToHtml($enml, $resourceMap);
            $results['warnings'] = array_merge($results['warnings'], $warnings);

            $sourceUrl = (string) ($el->{'note-attributes'}->{'source-url'} ?? '');
            $tags      = array_values(array_filter(array_map('strval', (array) $el->tag)));

            $noteId = $this->noteService->create([
                'title'        => $title,
                'content'      => $html,
                'content_type' => 'html',
                'notebook_id'  => $notebookId,
                'tags'         => $tags,
                'source_url'   => $sourceUrl ?: null,
                'created_at'   => $createdAt,
                'updated_at'   => $updatedAt,
            ]);

            // 添付ファイルを保存
            foreach ($resourceMap as $resource) {
                if (!isset($resource['data'])) {
                    continue;
                }
                $this->noteService->addAttachment(
                    $noteId,
                    $resource['filename'],
                    $resource['mime_type'],
                    $resource['data'],
                );
            }

            $results['imported']++;
        } catch (\Throwable $e) {
            $results['errors']++;
            $results['warnings'][] = "ノート「{$title}」のインポートに失敗: " . $e->getMessage();
        }
    }

    /**
     * <resource> 要素を MD5 ハッシュをキーに抽出する。
     * @return array<string, array{filename: string, mime_type: string, data: string}>
     */
    private function extractResourceMap(\SimpleXMLElement $noteEl): array
    {
        $map = [];
        foreach ($noteEl->resource as $res) {
            $rawData = (string) $res->data;
            if ($rawData === '') {
                continue;
            }
            // enex の data 要素は改行入り Base64
            $binary   = base64_decode(preg_replace('/\s+/', '', $rawData), true);
            if ($binary === false) {
                continue;
            }
            $hash     = md5($binary);
            $mimeType = (string) ($res->mime ?? 'application/octet-stream');
            $filename = (string) ($res->{'resource-attributes'}->filename ?? ($hash . '.' . $this->mimeToExt($mimeType)));

            $map[$hash] = [
                'filename'  => $filename,
                'mime_type' => $mimeType,
                'data'      => $binary,
            ];
        }
        return $map;
    }

    /**
     * ENML を SQNote HTML サブセットに変換する。
     * @param array<string, array{filename: string, mime_type: string, data: string}> $resourceMap
     * @return array{0: string, 1: list<string>}
     */
    private function enmlToHtml(string $enml, array &$resourceMap): array
    {
        // DOCTYPE 宣言と XML 宣言を除去
        $enml = preg_replace('/<!DOCTYPE[^>]*>/i', '', $enml);
        $enml = preg_replace('/<\?xml[^?]*\?>/i', '', $enml);
        $enml = trim($enml);

        if ($enml === '') {
            return ['', []];
        }

        $warnings = [];
        $dom      = new \DOMDocument('1.0', 'UTF-8');
        $dom->substituteEntities = false;

        // 名前空間エラーを抑制しつつロード
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($enml);
        libxml_clear_errors();

        if (!$loaded || $dom->documentElement === null) {
            return ['<p>（本文のパースに失敗しました）</p>', ["ENML パース失敗: 本文を読み込めませんでした"]];
        }

        // リソース ID の確定（インポート後は attachments.id で参照するが、
        // ここではハッシュをプレースホルダーとして使い、後から置換する）
        $this->transformChildren($dom->documentElement, $dom, $resourceMap, $warnings);

        $inner = '';
        foreach ($dom->documentElement->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return [$inner, $warnings];
    }

    private function transformChildren(
        \DOMNode $parent,
        \DOMDocument $dom,
        array &$resourceMap,
        array &$warnings,
    ): void {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (!($child instanceof \DOMElement)) {
                continue;
            }

            match ($child->nodeName) {
                'en-media' => $this->transformEnMedia($child, $parent, $dom, $resourceMap),
                'en-todo'  => $this->transformEnTodo($child, $parent, $dom),
                default    => $this->transformChildren($child, $dom, $resourceMap, $warnings),
            };
        }
    }

    private function transformEnMedia(
        \DOMElement $node,
        \DOMNode $parent,
        \DOMDocument $dom,
        array &$resourceMap,
    ): void {
        $hash     = (string) $node->getAttribute('hash');
        $mimeType = (string) $node->getAttribute('type');
        $resource = $resourceMap[$hash] ?? null;

        if ($resource === null) {
            $parent->removeChild($node);
            return;
        }

        if (str_starts_with($mimeType, 'image/')) {
            $img = $dom->createElement('img');
            $img->setAttribute('src', 'attachment://' . $hash);
            $img->setAttribute('data-sqnote-attachment-id', $hash);
            $img->setAttribute('alt', $resource['filename']);
            $parent->replaceChild($img, $node);
        } else {
            $a = $dom->createElement('a');
            $a->setAttribute('href', '/api/v1/attachments/' . $hash);
            $a->appendChild($dom->createTextNode($resource['filename']));
            $parent->replaceChild($a, $node);
        }
    }

    private function transformEnTodo(\DOMElement $node, \DOMNode $parent, \DOMDocument $dom): void
    {
        $input = $dom->createElement('input');
        $input->setAttribute('type', 'checkbox');
        $input->setAttribute('disabled', 'disabled');
        if ($node->getAttribute('checked') === 'true') {
            $input->setAttribute('checked', 'checked');
        }
        $parent->replaceChild($input, $node);
    }

    private function isDuplicate(string $title, int $createdAt, string $notebookId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM notes
             WHERE title = ? AND created_at = ? AND notebook_id = ? AND is_deleted = 0'
        );
        $stmt->execute([$title, $createdAt, $notebookId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function parseEnexDate(string $date): int
    {
        if ($date === '') {
            return time();
        }
        $dt = \DateTimeImmutable::createFromFormat('Ymd\THis\Z', $date, new \DateTimeZone('UTC'));
        return $dt !== false ? $dt->getTimestamp() : time();
    }

    private function mimeToExt(string $mime): string
    {
        return match ($mime) {
            'image/png'      => 'png',
            'image/jpeg'     => 'jpg',
            'image/gif'      => 'gif',
            'image/webp'     => 'webp',
            'application/pdf' => 'pdf',
            default          => 'bin',
        };
    }
}
