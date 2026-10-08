<?php

declare(strict_types=1);

use OCA\FlzPermissionMatrix\Service\GroupFoldersManagerProviderInterface;
use OCA\FlzPermissionMatrix\Service\NextcloudGroupFoldersSource;

class GroupFoldersManagerProviderFake implements GroupFoldersManagerProviderInterface {
    public int $calls = 0;

    public function __construct(private object $manager) {
    }

    public function manager(): object {
        $this->calls++;

        return $this->manager;
    }
}

$mountPoint = '/Personal/Vorstand';
$folder = new class($mountPoint) {
    public int $id = 47;
    public bool $acl = false;
    public array $groups = [
        'Betriebsrat' => ['displayName' => 'Betriebsrat', 'permissions' => 31, 'type' => 'group'],
    ];

    public function __construct(public string $mountPoint) {
    }
};
$manager = new class($folder) {
    public int $calls = 0;

    public function __construct(private object $folder) {
    }

    public function getAllFolders(): array {
        $this->calls++;

        return [47 => $this->folder];
    }
};
$provider = new GroupFoldersManagerProviderFake($manager);
$source = new NextcloudGroupFoldersSource($provider);
$read = $source->folders('groupfolders', '22.0.6');
assertSameValue(true, $read['complete'], 'The verified 22.x source shape should be readable.');
assertSameValue(1, $provider->calls, 'A supported version should resolve the native manager once.');
assertSameValue(1, $manager->calls, 'The source should enumerate Team Folders once through FolderManager.');
assertSameValue(['Betriebsrat' => 31], $read['folders'][0]['groups'], 'Only group ids and native permission masks should cross the source boundary.');
assertSameValue(false, $read['folders'][0]['advanced_permissions'], 'The native ACL flag should remain explicit.');
$serialized = json_encode($read, JSON_THROW_ON_ERROR);
assertSameValue(false, str_contains($serialized, $mountPoint), 'Mount paths must never leave the source adapter.');
assertSameValue(12, strlen($read['folders'][0]['reference']), 'Folder ids must be replaced by a stable short pseudonymous reference.');

$unsupportedProvider = new GroupFoldersManagerProviderFake($manager);
$unsupported = (new NextcloudGroupFoldersSource($unsupportedProvider))->folders('groupfolders', '23.0.0');
assertSameValue(false, $unsupported['complete'], 'Unverified future versions must fail closed.');
assertSameValue(0, $unsupportedProvider->calls, 'An unsupported version must not touch the foreign runtime API.');
assertContainsText('23.0.0', implode(' ', $unsupported['warnings']), 'The safe diagnostic should identify the incompatible version.');

$legacyProvider = new GroupFoldersManagerProviderFake($manager);
$legacy = (new NextcloudGroupFoldersSource($legacyProvider))->folders('files_groupfolders', '1.0.0');
assertSameValue(false, $legacy['complete'], 'The historical app id has no assumed compatibility contract.');
assertSameValue(0, $legacyProvider->calls, 'The legacy app must not be queried through a guessed runtime API.');

$aclFolder = clone $folder;
$aclFolder->acl = true;
$aclManager = new class($aclFolder) {
    public function __construct(private object $folder) {
    }
    public function getAllFolders(): array { return [$this->folder]; }
};
$acl = (new NextcloudGroupFoldersSource(new GroupFoldersManagerProviderFake($aclManager)))->folders('groupfolders', '22.1.0');
assertSameValue(false, $acl['complete'], 'Advanced nested ACLs must prevent a completeness claim.');
assertContainsText('Erweiterte ACLs', implode(' ', $acl['warnings']), 'Advanced ACL limitations must be visible.');

$circleFolder = clone $folder;
$circleFolder->groups = [
    'team-circle' => ['displayName' => 'Team', 'permissions' => 1, 'type' => 'circle'],
];
$circleManager = new class($circleFolder) {
    public function __construct(private object $folder) {
    }
    public function getAllFolders(): array { return [$this->folder]; }
};
$circle = (new NextcloudGroupFoldersSource(new GroupFoldersManagerProviderFake($circleManager)))->folders('groupfolders', '22.0.6');
assertSameValue(false, $circle['complete'], 'Circle/team mappings that cannot be projected to the group matrix must stay partial.');
assertSameValue([], $circle['folders'][0]['groups'], 'Circle ids must not be mislabeled as Nextcloud group ids.');

$brokenManager = new class {
    public function getAllFolders(): array {
        throw new RuntimeException('private mount /Personal/Secret');
    }
};
$broken = (new NextcloudGroupFoldersSource(new GroupFoldersManagerProviderFake($brokenManager)))->folders('groupfolders', '22.0.6');
assertSameValue(false, $broken['complete'], 'Foreign source failures must fail closed.');
assertSameValue(false, str_contains(implode(' ', $broken['warnings']), '/Personal/Secret'), 'Foreign exception messages must not leak into diagnostics.');

echo 'Nextcloud Groupfolders source tests passed' . PHP_EOL;
