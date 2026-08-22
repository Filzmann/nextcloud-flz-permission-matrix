<?php

declare(strict_types=1);

namespace OCP {
    interface IUser { public function getUID(); }
    interface IUserManager {
        public function search($pattern, $limit = null, $offset = null);
        public function getDisabledUsers(?int $limit = null, int $offset = 0, string $search = ''): array;
    }
    interface IAppConfig {
        public function getValueBool(string $appId, string $key, bool $default = false, bool $lazy = false): bool;
    }
}

namespace OCP\Share {
    interface IShare {
        public const TYPE_GROUP = 1;
        public function getFullId(): string;
        public function getSharedWith();
        public function getTarget();
        public function getNodeType();
        public function getPermissions();
        public function getParent(): ?int;
    }
    interface IManager {
        public function shareApiEnabled(): bool;
        public function allowGroupSharing(): bool;
        public function shareApiAllowLinks(): bool;
        public function shareApiLinkEnforcePassword(bool $checkGroupMembership = true): bool;
        public function shareApiLinkDefaultExpireDate(): bool;
        public function shareApiLinkDefaultExpireDateEnforced(): bool;
        public function shareApiLinkAllowPublicUpload(): bool;
        public function shareWithGroupMembersOnly(): bool;
        public function shareApiInternalDefaultExpireDate(): bool;
        public function shareApiRemoteDefaultExpireDate(): bool;
        public function getSharesBy(string $userId, int $shareType, $path = null, bool $reshares = false, int $limit = 50, int $offset = 0, bool $onlyValid = true): array;
    }
}

namespace {
    use OCA\BrPermissionMatrix\Service\NextcloudSharingSource;
    use OCP\IAppConfig;
    use OCP\IUser;
    use OCP\IUserManager;
    use OCP\Share\IManager;
    use OCP\Share\IShare;

    class NativeSourceUser implements IUser {
        public function __construct(private string $uid) {
        }

        public function getUID(): string {
            return $this->uid;
        }
    }

    class NativeSourceShare implements IShare {
        public function __construct(
            private string $id,
            private string $group,
            private string $target,
            private string $type,
            private int $permissions
        ) {
        }

        public function getFullId(): string { return $this->id; }
        public function getSharedWith(): string { return $this->group; }
        public function getTarget(): string { return $this->target; }
        public function getNodeType(): string { return $this->type; }
        public function getPermissions(): int { return $this->permissions; }
        public function getParent(): ?int { return null; }
    }

    class NativeSourceUsers implements IUserManager {
        public function __construct(private bool $fail = false) {
        }

        public function search($pattern, $limit = null, $offset = null): array {
            if ($this->fail) {
                throw new RuntimeException('backend includes a private identifier');
            }

            return $offset === 0 ? [new NativeSourceUser('alice')] : [];
        }

        public function getDisabledUsers(?int $limit = null, int $offset = 0, string $search = ''): array {
            return $offset === 0 ? [new NativeSourceUser('disabled-user')] : [];
        }
    }

    class NativeSourceManager implements IManager {
        public array $shareCalls = [];
        private IShare $share;

        public function __construct() {
            $this->share = new NativeSourceShare('provider:4711', 'Betriebsrat', '/BR/Ordner', 'folder', 31);
        }

        public function shareApiEnabled(): bool { return true; }
        public function allowGroupSharing(): bool { return true; }
        public function shareApiAllowLinks(): bool { return false; }
        public function shareApiLinkEnforcePassword(bool $checkGroupMembership = true): bool { return true; }
        public function shareApiLinkDefaultExpireDate(): bool { return true; }
        public function shareApiLinkDefaultExpireDateEnforced(): bool { return true; }
        public function shareApiLinkAllowPublicUpload(): bool { return false; }
        public function shareWithGroupMembersOnly(): bool { return true; }
        public function shareApiInternalDefaultExpireDate(): bool { return true; }
        public function shareApiRemoteDefaultExpireDate(): bool { return false; }

        public function getSharesBy(string $userId, int $shareType, $path = null, bool $reshares = false, int $limit = 50, int $offset = 0, bool $onlyValid = true): array {
            $this->shareCalls[] = [$userId, $shareType, $reshares, $limit, $offset, $onlyValid];

            return $offset === 0 ? [$this->share] : [];
        }
    }

    class NativeSourceConfig implements IAppConfig {
        public function getValueBool(string $appId, string $key, bool $default = false, bool $lazy = false): bool {
            return $key === 'shareapi_allow_resharing';
        }
    }

    $manager = new NativeSourceManager();
    $source = new NextcloudSharingSource($manager, new NativeSourceUsers(), new NativeSourceConfig());
    $policies = $source->policies();
    assertSameValue(true, $policies['values']['sharing']['value'], 'The source should use the public native sharing manager for global sharing.');
    assertSameValue(false, $policies['values']['link_sharing']['value'], 'Native denies must remain visible.');
    assertSameValue(true, $policies['values']['resharing']['value'], 'The canonical core setting should cover the policy missing from IManager.');

    $result = $source->groupShares();
    assertSameValue(true, $result['complete'], 'Successful active and disabled user enumeration should be complete.');
    assertSameValue(1, count($result['shares']), 'Duplicate provider shares should be deduplicated by their full id.');
    assertSameValue('Betriebsrat', $result['shares'][0]['group_id'], 'The public share recipient should be retained.');
    assertSameValue('/BR/Ordner', $result['shares'][0]['target'], 'The recipient-relative public target should be retained.');
    assertSameValue(false, $result['shares'][0]['inherited'], 'A share without a public parent should be identified as direct.');
    assertSameValue(2, count($manager->shareCalls), 'Active and disabled owners should both be inspected.');
    foreach ($manager->shareCalls as $call) {
        assertSameValue(true, $call[2], 'Reshares should be included in the read-only inventory.');
        assertSameValue(false, $call[5], 'onlyValid must be false because the native API may delete invalid shares otherwise.');
    }
    assertSameValue(false, str_contains(json_encode($result, JSON_THROW_ON_ERROR), 'alice'), 'Transient owner ids must not enter the source result.');
    assertSameValue(false, str_contains(json_encode($result, JSON_THROW_ON_ERROR), 'disabled-user'), 'Disabled owner ids must not enter the source result.');

    $failed = (new NextcloudSharingSource(new NativeSourceManager(), new NativeSourceUsers(true), new NativeSourceConfig()))->groupShares();
    assertSameValue(false, $failed['complete'], 'A user backend failure must produce an incomplete result.');
    assertSameValue([], $failed['shares'], 'A failed owner inventory must not claim a complete subset.');
    assertSameValue(false, str_contains(implode(' ', $failed['warnings']), 'private identifier'), 'Backend exception details must remain out of diagnostics.');

    echo 'NextcloudSharingSource tests passed' . PHP_EOL;
}
