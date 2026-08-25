<?php

declare(strict_types=1);

return [
    'db' => [
        'path'       => getenv('SQNOTE_DB_PATH') ?: __DIR__ . '/../data/notes.sqnote',
        'passphrase' => getenv('SQNOTE_DB_PASS') ?: '',
    ],
    'auth' => [
        'user' => getenv('SQNOTE_BASIC_USER') ?: 'tokita',
        'pass' => getenv('SQNOTE_BASIC_PASS') ?: 'hoge2hoge',
    ],
    'session' => [
        'lifetime'  => (int)(getenv('SQNOTE_SESSION_LIFETIME') ?: 604800),
        'name'      => 'sqnote_sess',
        'save_path' => getenv('SQNOTE_SESSION_PATH') ?: __DIR__ . '/../data/sessions',
    ],
];
