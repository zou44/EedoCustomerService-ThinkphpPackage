<?php

$DS = DIRECTORY_SEPARATOR;
return [
    'paths'         => [
        'migrations' => realpath(__DIR__) . "{$DS}"."..{$DS}"."db{$DS}"."migrations",
        'seeds'      => realpath(__DIR__) . "{$DS}"."..{$DS}"."db{$DS}"."seeds",
    ],
    'environments'  => [
        'default_migration_table' => 'eedo_phinxlog',
        'default_environment'     => 'default',
        'default'                 => [
            'adapter'      => env('EEDO_PHINX_ADAPTER', 'mysql'),
            'host'         => env('EEDO_PHINX_HOST', 'localhost'),
            'name'         => env('EEDO_PHINX_NAME', ''),
            'user'         => env('EEDO_PHINX_USER', ''),
            'pass'         => env('EEDO_PHINX_PASS', ''),
            'port'         => env('EEDO_PHINX_PORT', '3306'),
            'charset'      => env('EEDO_PHINX_CHARSET', 'utf8mb4'),
            'table_prefix' => env('EEDO_PHINX_TABLE_PREFIX', 'eedo_'),
        ],
    ],
    'version_order' => 'creation',
];
