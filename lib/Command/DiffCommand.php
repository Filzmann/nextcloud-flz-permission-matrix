<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Command;

use OCA\FlzPermissionMatrix\Db\SnapshotMapper;
use OCA\FlzPermissionMatrix\Service\DiffService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DiffCommand extends Command {
    public function __construct(
        private SnapshotMapper $snapshots,
        private DiffService $diffs
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('permission-matrix:diff')
            ->setDescription('Vergleicht zwei Snapshots.')
            ->addArgument('snapshot-a', InputArgument::REQUIRED, 'Ausgangs-Snapshot')
            ->addArgument('snapshot-b', InputArgument::REQUIRED, 'Ziel-Snapshot');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $a = $this->snapshots->find((string)$input->getArgument('snapshot-a'));
        $b = $this->snapshots->find((string)$input->getArgument('snapshot-b'));
        if ($a === null || $b === null) {
            $output->writeln('Snapshot nicht gefunden.');

            return self::FAILURE;
        }

        foreach ($this->diffs->compareSnapshots($a, $b) as $diff) {
            $output->writeln(sprintf('[%s] %s %s', $diff['severity'] ?? 'info', $diff['type'] ?? 'PERMISSION_CHANGED', $diff['message'] ?? ''));
        }

        return self::SUCCESS;
    }
}
