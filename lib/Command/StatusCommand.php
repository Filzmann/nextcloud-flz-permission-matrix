<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Command;

use OCA\FilzmannPermissionMatrix\Db\SnapshotMapper;
use OCA\FilzmannPermissionMatrix\Service\BaselineService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command {
    public function __construct(
        private SnapshotMapper $snapshots,
        private BaselineService $baseline
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('permission-matrix:status')
            ->setDescription('Zeigt Scan-, Baseline- und Compliance-Status.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $latest = $this->snapshots->latest();
        $output->writeln('Baseline: ' . ($this->baseline->currentBaselineId() ?: 'nicht gesetzt'));
        if ($latest === null) {
            $output->writeln('Letzter Snapshot: keiner');

            return self::SUCCESS;
        }

        $summary = $latest->summary();
        $output->writeln('Letzter Snapshot: ' . $latest->snapshotId());
        $output->writeln('Stand: ' . $latest->createdAt());
        $output->writeln('Compliance: ' . ($summary['compliance_status'] ?? 'UNKNOWN'));
        $output->writeln('Warnungen: ' . (string)($summary['warning_count'] ?? 0));
        $output->writeln('Nicht unterstuetzte Apps: ' . (string)($summary['unsupported_count'] ?? 0));

        return self::SUCCESS;
    }
}
