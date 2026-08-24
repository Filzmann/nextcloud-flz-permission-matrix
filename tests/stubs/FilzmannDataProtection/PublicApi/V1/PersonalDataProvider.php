<?php

declare(strict_types=1);

namespace OCA\FilzmannDataProtection\PublicApi\V1;

interface PersonalDataProvider {
    public function descriptor(): ProviderDescriptor;
    public function collect(PersonalDataRequest $request): PersonalDataPage;
}
