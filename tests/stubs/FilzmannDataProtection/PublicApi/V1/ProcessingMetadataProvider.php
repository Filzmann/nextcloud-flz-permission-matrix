<?php

declare(strict_types=1);

namespace OCA\FilzmannDataProtection\PublicApi\V1;

interface ProcessingMetadataProvider {
    public function descriptor(): ProcessingMetadataProviderDescriptor;
    public function catalog(): ProcessingMetadataCatalog;
}
