<?php

declare(strict_types=1);

$roadmap = (string)file_get_contents(dirname(__DIR__, 2) . '/ROADMAP.md');

assertContainsText(
    'bei jeder relevanten Weiterentwicklung',
    $roadmap,
    'Owned apps must maintain their provider projection with each relevant change.',
);
assertContainsText(
    'Fremd-App-Coverage',
    $roadmap,
    'Third-party apps need a separate explicit coverage strategy.',
);
assertContainsText(
    'UNSUPPORTED',
    $roadmap,
    'Missing third-party contracts must never become inferred grants.',
);

echo 'Permission provider roadmap contract tests passed' . PHP_EOL;
