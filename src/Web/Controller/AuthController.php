<?php

declare(strict_types=1);

namespace SQNote\Web\Controller;

use SQNote\Auth\SessionAuth;
use Twig\Environment;

class AuthController extends AbstractController
{
    public function __construct(
        Environment $twig,
        private SessionAuth $sessionAuth,
        private array $authConfig,
    ) {
        parent::__construct($twig);
    }

    public function loginForm(): never
    {
        $this->render('auth/login.html.twig', [
            'csrf_token' => $this->sessionAuth->getCsrfToken(),
            'error'      => null,
            'redirect_to' => $_GET['redirect_to'] ?? '/',
        ]);
    }

    public function loginPost(): never
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!$this->sessionAuth->verifyCsrfToken($token)) {
            http_response_code(403);
            $this->render('auth/login.html.twig', [
                'csrf_token'  => $this->sessionAuth->getCsrfToken(),
                'error'       => 'セッションが無効です。もう一度お試しください。',
                'redirect_to' => $_POST['redirect_to'] ?? '/',
            ]);
        }

        $user = $_POST['user'] ?? '';
        $pass = $_POST['pass'] ?? '';

        if ($this->sessionAuth->login($user, $pass, $this->authConfig['user'], $this->authConfig['pass'])) {
            $redirectTo = $_POST['redirect_to'] ?? '/';
            // オープンリダイレクト防止: 相対パスのみ許可
            if (!str_starts_with($redirectTo, '/') || str_starts_with($redirectTo, '//')) {
                $redirectTo = '/';
            }
            $this->redirect($redirectTo);
        }

        $this->render('auth/login.html.twig', [
            'csrf_token'  => $this->sessionAuth->getCsrfToken(),
            'error'       => 'ユーザー名またはパスワードが正しくありません。',
            'redirect_to' => $_POST['redirect_to'] ?? '/',
        ]);
    }

    public function logout(): never
    {
        $this->sessionAuth->logout();
        $this->redirect('/login');
    }
}
