<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\PublicApi\V1;

interface PermissionProvider {
    public function descriptor(): PermissionProviderDescriptor;
    public function collect(): PermissionProviderResult;
}
