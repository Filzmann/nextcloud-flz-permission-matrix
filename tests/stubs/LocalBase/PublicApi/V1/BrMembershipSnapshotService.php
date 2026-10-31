<?php

declare(strict_types=1);

namespace OCA\LocalBase\PublicApi\V1;

/** App-lokaler Test-Double; die reale DI-Grenze wird im Parent und in Nextcloud geprüft. */
final class BrMembershipSnapshotService {
    public function __construct(private BrMembershipSnapshot $snapshot) {
    }

    public function snapshot(): BrMembershipSnapshot {
        return $this->snapshot;
    }
}
