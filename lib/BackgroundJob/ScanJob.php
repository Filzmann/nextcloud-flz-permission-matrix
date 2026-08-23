<?php

declare(strict_types=1);

namespace OCA\FilzmannPermissionMatrix\BackgroundJob;

use DateTimeImmutable;
use OCA\FilzmannPermissionMatrix\Db\SnapshotMapper;
use OCA\FilzmannPermissionMatrix\Service\ConfigService;
use OCA\FilzmannPermissionMatrix\Service\ScannerService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

class ScanJob extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private ScannerService $scanner,
        private SnapshotMapper $snapshots,
        private ConfigService $config,
        private LoggerInterface $logger
    ) {
        parent::__construct($time);
        $this->setInterval(60 * 60);
    }

    protected function run($argument): void {
        try {
            if (!$this->isDue()) {
                return;
            }
            $this->scanner->scan(null);
        } catch (\Throwable $e) {
            $this->logger->error('Permission matrix background scan failed', [
                'app' => 'filzmann_permission_matrix',
                'exception' => $e,
            ]);
        }
    }

    private function isDue(): bool {
        $latest = $this->snapshots->latest();
        if ($latest === null) {
            return true;
        }

        $age = time() - (new DateTimeImmutable($latest->createdAt()))->getTimestamp();
        $interval = match ($this->config->scanInterval()) {
            'hourly' => 60 * 60,
            'weekly' => 7 * 24 * 60 * 60,
            default => 24 * 60 * 60,
        };

        return $age >= $interval;
    }
}
