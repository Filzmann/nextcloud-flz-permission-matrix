<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Adapter;

use OCA\BrPermissionMatrix\Model\MatrixRow;

/**
 * Zweck: Unveraenderliches Sammelergebnis eines oder mehrerer read-only Adapter.
 *
 * Zusammenspiel:
 * - MatrixBuilder fuehrt die Einzelergebnisse per merge() zusammen und uebergibt Zeilen,
 *   Warnungen sowie Adapterstatus als einen konsistenten Scan-Baustein an ScannerService.
 */
class AdapterResult {
    /**
     * @param MatrixRow[] $rows
     */
    public function __construct(
        private array $rows = [],
        private array $warnings = [],
        private array $unsupportedApps = [],
        private array $adapterStatus = []
    ) {
    }

    public static function empty(): self {
        return new self();
    }

    public function merge(self $other): self {
        return new self(
            [...$this->rows, ...$other->rows],
            array_values(array_unique([...$this->warnings, ...$other->warnings])),
            array_values(array_unique([...$this->unsupportedApps, ...$other->unsupportedApps])),
            [...$this->adapterStatus, ...$other->adapterStatus]
        );
    }

    /**
     * @return MatrixRow[]
     */
    public function rows(): array {
        return $this->rows;
    }

    public function warnings(): array {
        return $this->warnings;
    }

    public function unsupportedApps(): array {
        return $this->unsupportedApps;
    }

    public function adapterStatus(): array {
        return $this->adapterStatus;
    }
}
