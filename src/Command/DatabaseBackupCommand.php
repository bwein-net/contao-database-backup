<?php

/*
 * This file is part of Database Backup for Contao Open Source CMS.
 *
 * (c) bwein.net
 *
 * @license MIT
 */

namespace Bwein\DatabaseBackup\Command;

use Bwein\DatabaseBackup\Service\DatabaseBackupDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseBackupCommand extends Command
{
    protected $dumper;

    /**
     * DatabaseBackupCommand constructor.
     */
    public function __construct(DatabaseBackupDumper $dumper)
    {
        parent::__construct();
        $this->dumper = $dumper;
    }

    protected function configure()
    {
        $this->setName('bwein:database:backup')
            ->setDescription('Database backup.')
            ->addArgument(
                'type',
                InputArgument::OPTIONAL,
                'Type of database backup. Allowed parameters are: '.implode(',', DatabaseBackupDumper::getBackupTypes())
            )
            ->addArgument(
                'filename',
                InputArgument::OPTIONAL,
                'Filename for the file you want to backup database to. Every file will be created in var/backups directory'
            );
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $backupType = $input->getArgument('type');
        $filename = $input->getArgument('filename');
        $this->dumper->doBackup($backupType, $filename, $output);
    }
}
