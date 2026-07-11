<?php

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'api#state', 'url' => '/api/state', 'verb' => 'GET'],
        ['name' => 'api#scan', 'url' => '/api/scan', 'verb' => 'POST'],
        ['name' => 'api#snapshots', 'url' => '/api/snapshots', 'verb' => 'GET'],
        ['name' => 'api#setBaseline', 'url' => '/api/baseline/{snapshotId}', 'verb' => 'POST'],
        ['name' => 'api#diff', 'url' => '/api/diff/{snapshotA}/{snapshotB}', 'verb' => 'GET'],
        ['name' => 'api#exportLatest', 'url' => '/api/export/{format}', 'verb' => 'GET'],
        ['name' => 'api#exportSnapshot', 'url' => '/api/export/{format}/{snapshotId}', 'verb' => 'GET'],
        ['name' => 'config#get', 'url' => '/api/config', 'verb' => 'GET'],
        ['name' => 'config#save', 'url' => '/api/config', 'verb' => 'POST'],
    ],
];
