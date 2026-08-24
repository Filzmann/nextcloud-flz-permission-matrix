<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\Command;

use OCA\FilzmannPermissionMatrix\Db\SnapshotMapper;
use OCA\FilzmannPermissionMatrix\Service\ExportService;
use OCA\FilzmannPermissionMatrix\Service\ScannerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends Command {
    public function __construct(
        private SnapshotMapper $snapshots,
        private ScannerService $scanner,
        private ExportService $exports
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setName('permission-matrix:export')
            ->setDescription('Exportiert den letzten Snapshot als md, csv, json oder html.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Exportformat', 'md')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Ausgabedatei');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $snapshot = $this->snapshots->latest() ?? $this->scanner->scan(null);
        $export = $this->exports->export($snapshot, (string)$input->getOption('format'));
        $target = (string)($input->getOption('output') ?: $export['filename']);
        file_put_contents($target, $export['content']);
        $output->writeln('Export: ' . $target);

        return self::SUCCESS;
    }
}
