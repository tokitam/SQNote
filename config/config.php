<?php

declare(strict_types=1);

return [
    'db' => [
        'path'       => getenv('SQNOTE_DB_PATH') ?: __DIR__ . '/../data/notes.sqnote',
        'passphrase' => getenv('SQNOTE_DB_PASS') ?: '',
    ],
    'auth' => [
        'user' => getenv('SQNOTE_BASIC_USER') ?: 'admin',
        'pass' => getenv('SQNOTE_BASIC_PASS') ?: '',
    ],
];
