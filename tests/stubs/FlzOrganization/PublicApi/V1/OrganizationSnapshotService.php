<?php

declare(strict_types=1);

namespace OCA\FlzOrganization\PublicApi\V1;

/** App-local consumer double; real DI behavior is verified by the provider app. */
final class OrganizationSnapshotService {
    public function __construct(private object $snapshot) {}

    public function snapshot(): object { return $this->snapshot; }
}
