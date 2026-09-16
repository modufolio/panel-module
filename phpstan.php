<?php

return [
    'includes' => [
        __DIR__ . '/vendor/phpstan/phpstan-phpunit/extension.neon',
    ],
    'parameters' => [
        'level' => 8,
        'paths' => ['src', 'tests'],
        'tmpDir' => '.phpstan.cache',
    ],
];
