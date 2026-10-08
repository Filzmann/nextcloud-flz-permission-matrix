<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Command;

use OCA\FlzPermissionMatrix\Service\ScannerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ScanCommand extends Command {
    public function __construct(
        private ScannerService $scanner
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('permission-matrix:scan')
            ->setDescription('Erzeugt einen neuen read-only Berechtigungsmatrix-Snapshot.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $snapshot = $this->scanner->scan(null);
        $output->writeln('Snapshot: ' . $snapshot->snapshotId());
        $output->writeln('Compliance: ' . ($snapshot->summary()['compliance_status'] ?? 'UNKNOWN'));

        return self::SUCCESS;
    }
}
