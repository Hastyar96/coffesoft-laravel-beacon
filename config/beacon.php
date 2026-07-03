<?php

declare(strict_types=1);

return [
    'enabled' => true,
    'output_directory' => 'storage/app/beacon',
    'exporters' => [
        'markdown' => true,
        'json' => false,
        'graph' => false,
    ],
    'project_name' => null,
];