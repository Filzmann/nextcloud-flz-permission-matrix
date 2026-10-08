<?php

declare(strict_types=1);

namespace OCA\LocalBase\PublicApi\V1;

/** App-lokaler Test-Double; die reale DI-Grenze wird im Parent und in Nextcloud geprüft. */
final class OrganizationSnapshotService {
    public function __construct(private OrganizationSnapshot $snapshot) {
    }

    public function snapshot(): OrganizationSnapshot {
        return $this->snapshot;
    }
}
