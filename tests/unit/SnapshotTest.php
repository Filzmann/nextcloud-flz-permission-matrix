<?php

declare(strict_types=1);

use OCA\FilzmannPermissionMatrix\Model\MatrixRow;
use OCA\FilzmannPermissionMatrix\Model\Snapshot;

$row = static fn(string $appId, string $object): MatrixRow => new MatrixRow(
    'Permission',
    $appId,
    $object,
    'Detail',
    'Recht',
    'NEW',
    'test',
    'high',
    ['Testgruppe' => 'X']
);

$snapshot = new Snapshot(
    'pm-order-test',
    '2026-07-11T12:00:00+00:00',
    '34.0.0',
    ['Testgruppe'],
    [],
    [$row('z-app', 'Zweites Recht'), $row('a-app', 'Z-Recht'), $row('a-app', 'A-Recht')]
);

assertSameValue(
    ['a-app:A-Recht', 'a-app:Z-Recht', 'z-app:Zweites Recht'],
    array_map(static fn(MatrixRow $item): string => $item->appId() . ':' . $item->objectName(), $snapshot->matrix()),
    'Snapshot rows must remain strictly ordered by app and permission in every consumer.'
);

echo 'Snapshot tests passed' . PHP_EOL;
