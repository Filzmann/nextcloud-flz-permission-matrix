<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Privacy;

use DomainException;
use InvalidArgumentException;
use JsonException;
use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataCatalog;
use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataProvider;
use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataProviderDescriptor;
use OCA\FlzPermissionMatrix\AppInfo\AppId;

final class PermissionMatrixProcessingMetadataProvider implements ProcessingMetadataProvider {
    public function descriptor(): ProcessingMetadataProviderDescriptor {
        return new ProcessingMetadataProviderDescriptor(AppId::VALUE, 'Berechtigungsmatrix', '1.0');
    }

    public function catalog(): ProcessingMetadataCatalog {
        $path = dirname(__DIR__, 2) . '/resources/privacy-processing.json';
        if (!is_file($path)) {
            throw new DomainException('Processing metadata catalog unavailable.');
        }

        try {
            $content = file_get_contents($path);
            if ($content === false) {
                throw new DomainException('Processing metadata catalog unavailable.');
            }
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new DomainException('Processing metadata catalog is invalid.');
            }
            return ProcessingMetadataCatalog::fromArray($payload);
        } catch (JsonException|InvalidArgumentException) {
            throw new DomainException('Processing metadata catalog is invalid.');
        }
    }
}
