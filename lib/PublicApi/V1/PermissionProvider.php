<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\PublicApi\V1;

interface PermissionProvider {
    public function descriptor(): PermissionProviderDescriptor;
    public function collect(): PermissionProviderResult;
}
