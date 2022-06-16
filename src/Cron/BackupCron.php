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

use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Contao\CoreBundle\Monolog\ContaoContext;
use Psr\Log\LoggerInterface;

class BackupCron
{
    protected BackupManager $backupManager;
    protected LoggerInterface $logger;

    public function __construct(BackupManager $backupManager, LoggerInterface $logger)
    {
        $this->backupManager = $backupManager;
        $this->logger = $logger;
    }

    public function __invoke(): void
    {
        try {
            $config = $this->backupManager->createCreateConfig();
            $this->backupManager->create($config);
        } catch (\Exception $exception) {
            $this->logger->error(
                $exception->getMessage(),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
        }
    }
}
