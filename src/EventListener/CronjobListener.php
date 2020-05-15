<?php

/*
 * This file is part of Database Backup for Contao Open Source CMS.
 *
 * (c) bwein.net
 *
 * @license MIT
 */

namespace Bwein\DatabaseBackup\EventListener;

use Bwein\DatabaseBackup\Service\DatabaseBackupDumper;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Exception;
use Psr\Log\LoggerInterface;

class CronjobListener
{
    protected $framework;
    protected $dumper;
    protected $logger;

    /**
     * CronjobListener constructor.
     */
    public function __construct(
        ContaoFramework $framework,
        DatabaseBackupDumper $dumper,
        LoggerInterface $logger
    ) {
        $this->framework = $framework;
        $this->dumper = $dumper;
        $this->logger = $logger;
    }

    public function onDaily()
    {
        try {
            $this->framework->initialize();
            $this->dumper->doBackup('auto');
        } catch (Exception $exception) {
            $this->logger->error(
                $exception->getMessage(),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
            );
        }
    }
}
