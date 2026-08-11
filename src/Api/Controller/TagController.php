<?php

declare(strict_types=1);

namespace SQNote\Api\Controller;

use SQNote\Repository\TagRepository;

class TagController extends AbstractController
{
    public function __construct(private TagRepository $tags) {}

    public function index(): never
    {
        $this->json($this->tags->findAll());
    }

    public function delete(array $params): never
    {
        $tag = $this->tags->findById($params['id']);
        if (!$tag) {
            $this->notFound('Tag');
        }
        $this->tags->delete($params['id']);
        $this->json(null);
    }
}
