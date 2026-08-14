<?php

declare(strict_types=1);

namespace SQNote\Support;

final class DiskFull
{
    /** @var string[] */
    private const MESSAGE_MARKERS = [
        'disk full',
        'disk is full',
        'no space left on device',
        'sqlite_full',
    ];

    public static function isCausedBy(\Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            $message = mb_strtolower($current->getMessage());
            foreach (self::MESSAGE_MARKERS as $marker) {
                if (str_contains($message, $marker)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function freeBytes(string $dbPath): ?int
    {
        $dir   = is_dir($dbPath) ? $dbPath : dirname($dbPath);
        $bytes = @disk_free_space($dir);

        return $bytes === false ? null : (int) $bytes;
    }

    public static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '不明';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $i     = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 1) . ' ' . $units[$i];
    }

    public static function renderHtml(?int $freeBytes): string
    {
        $free = htmlspecialchars(self::formatBytes($freeBytes), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ディスク容量不足 - SQNote</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Hiragino Sans, Meiryo, sans-serif;
         background: #f5f5f5; color: #222; margin: 0; padding: 2rem 1rem; }
  .panel { max-width: 32rem; margin: 4rem auto; background: #fff; border: 1px solid #e0b4b4;
           border-radius: 8px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
  h1 { font-size: 1.3rem; color: #a33; margin-top: 0; }
  p { line-height: 1.7; }
  .free { font-weight: bold; }
  a.retry { display: inline-block; margin-top: 1rem; padding: .5rem 1.2rem; background: #333;
            color: #fff; text-decoration: none; border-radius: 4px; }
</style>
</head>
<body>
  <div class="panel">
    <h1>サーバーのディスク容量が不足しています</h1>
    <p>保存先のディスクの空き容量が不足しているため、データを保存できませんでした。
       サーバーの空き容量を確保してから、もう一度お試しください。</p>
    <p>現在の空き容量（推定）: <span class="free">{$free}</span></p>
    <a class="retry" href="javascript:location.reload()">再読み込み</a>
  </div>
</body>
</html>
HTML;
    }
}
