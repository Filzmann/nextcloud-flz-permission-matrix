<?php

declare(strict_types=1);

namespace OCA\FlzPermissionMatrix\Command;

use OCA\FlzPermissionMatrix\Service\BaselineService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaselineSetCommand extends Command {
    public function __construct(
        private BaselineService $baseline
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('permission-matrix:baseline:set')
            ->setDescription('Setzt einen Snapshot als gueltige Positivlisten-Baseline.')
            ->addArgument('snapshot-id', InputArgument::REQUIRED, 'Snapshot-ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $snapshot = $this->baseline->setBaseline((string)$input->getArgument('snapshot-id'));
        $output->writeln('Baseline: ' . $snapshot->snapshotId());

        return self::SUCCESS;
    }
}
