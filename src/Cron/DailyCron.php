<?php

declare(strict_types=1);

/*
 * This file is part of Database Backup for Contao Open Source CMS.
 *
 * (c) bwein.net
 *
 * @license MIT
 */

namespace Bwein\DatabaseBackup\Cron;

use Bwein\DatabaseBackup\Service\DatabaseBackupDumper;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * @CronJob("daily")
 */
class DailyCron
{
    protected $dumper;
    protected $logger;

    public function __construct(DatabaseBackupDumper $dumper, LoggerInterface $logger)
    {
        $this->dumper = $dumper;
        $this->logger = $logger;
    }

    public function __invoke(string $scope): void
    {
        try {
            $this->dumper->doBackup('auto');
        } catch (Exception $exception) {
            $this->logger->error(
                $exception->getMessage(),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
        }
    }
}
