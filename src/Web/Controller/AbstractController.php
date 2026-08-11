<?php

declare(strict_types=1);

namespace SQNote\Web\Controller;

use Twig\Environment;

abstract class AbstractController
{
    public function __construct(protected Environment $twig) {}

    protected function render(string $template, array $vars = []): never
    {
        echo $this->twig->render($template, $vars);
        exit;
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}
