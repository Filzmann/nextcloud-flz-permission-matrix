<?php

declare(strict_types=1);

use OCA\BrPermissionMatrix\Model\MatrixRow;
use OCA\BrPermissionMatrix\Model\Snapshot;
use OCA\BrPermissionMatrix\Service\DiffService;

$baselineRow = new MatrixRow('App', 'deck', 'Deck', 'App-Nutzung', 'App-Verfuegbarkeit', 'APPROVED', 'core-app-config', 'high', [
    'Betriebsrat' => '-',
    'IKT-Ausschuss' => 'X',
]);
$currentRow = new MatrixRow('App', 'deck', 'Deck', 'App-Nutzung', 'App-Verfuegbarkeit', 'NEW', 'core-app-config', 'high', [
    'Betriebsrat' => 'X',
    'IKT-Ausschuss' => 'X',
]);
$baseline = new Snapshot('base', '2026-07-05T10:00:00+00:00', '34.0.0', ['Betriebsrat', 'IKT-Ausschuss'], [], [$baselineRow]);
$current = new Snapshot('current', '2026-07-05T11:00:00+00:00', '34.0.0', ['Betriebsrat', 'IKT-Ausschuss'], [], [$currentRow]);

[$rows, $diffs] = (new DiffService())->applyBaseline($current, $baseline, true);

assertSameValue('NOT_APPROVED', $rows[0]->status(), 'expanded app availability should be not approved in strict mode');
assertSameValue('APP_GROUP_EXPANDED', $diffs[0]['type'], 'diff type should classify app group expansion');
assertSameValue('critical', $diffs[0]['severity'], 'expansion should be critical');

echo 'DiffService tests passed' . PHP_EOL;
