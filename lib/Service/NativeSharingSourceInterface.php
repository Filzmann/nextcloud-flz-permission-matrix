<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Service;

/**
 * Zweck: Begrenzt den Sharing-Adapter auf die oeffentlich auslesbaren Nextcloud-Daten.
 *
 * Vertrag:
 * - Owner-IDs dienen nur transient zur vollstaendigen Abfrage und verlassen die Implementierung nicht.
 * - groupShares() verwendet ausschliesslich Leseoperationen und meldet Teilstaende explizit.
 * - Exceptions, Credentials und Dateiinhalte sind niemals Teil des Ergebnisses.
 */
interface NativeSharingSourceInterface {
    /**
     * @return array{values: array<string, array{value: ?bool, source: string, confidence: string}>, warnings: string[]}
     */
    public function policies(): array;

    /**
     * @return array{shares: array<int, array{reference: string, group_id: string, target: string, node_type: string, permissions: int, inherited: bool}>, complete: bool, warnings: string[]}
     */
    public function groupShares(): array;
}
