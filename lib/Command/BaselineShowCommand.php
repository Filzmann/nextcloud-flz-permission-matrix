<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Command;

use OCA\FlzPermissionMatrix\Service\BaselineService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaselineShowCommand extends Command {
    public function __construct(
        private BaselineService $baseline
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('permission-matrix:baseline:show')
            ->setDescription('Zeigt die aktuell konfigurierte Baseline.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $baseline = $this->baseline->currentBaseline();
        if ($baseline === null) {
            $output->writeln('Keine Baseline gesetzt.');

            return self::SUCCESS;
        }

        $output->writeln('Baseline: ' . $baseline->snapshotId());
        $output->writeln('Stand: ' . $baseline->createdAt());

        return self::SUCCESS;
    }
}
