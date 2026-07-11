<?php

declare(strict_types=1);

namespace OCA\BrPermissionMatrix\Service;

use DomainException;
use OCA\BrPermissionMatrix\Db\SnapshotMapper;
use OCA\BrPermissionMatrix\Model\Snapshot;

class BaselineService {
    public function __construct(
        private ConfigService $config,
        private SnapshotMapper $snapshots
    ) {
    }

    public function currentBaselineId(): string {
        return $this->config->baselineSnapshot();
    }

    public function currentBaseline(): ?Snapshot {
        $baselineId = $this->currentBaselineId();
        if ($baselineId === '') {
            return null;
        }

        return $this->snapshots->find($baselineId);
    }

    public function setBaseline(string $snapshotId): Snapshot {
        $snapshot = $this->snapshots->find($snapshotId);
        if ($snapshot === null) {
            throw new DomainException('Snapshot nicht gefunden.');
        }

        $this->config->setBaselineSnapshot($snapshotId);

        return $snapshot;
    }
}
