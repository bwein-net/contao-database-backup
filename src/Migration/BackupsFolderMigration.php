<?php

declare(strict_types=1);

/*
 * This file is part of Database Backup for Contao Open Source CMS.
 *
 * (c) bwein.net
 *
 * @license MIT
 */

namespace Bwein\DatabaseBackup\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class BackupsFolderMigration extends AbstractMigration
{
    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly Filesystem $filesystem,
    ) {
    }

    public function shouldRun(): bool
    {
        try {
            $finder = new Finder();
            $finder->in($this->params->get('kernel.project_dir').'/var/db_backups/');
            $finder->files()->name('*.sql.gz');

            return $finder->count() > 0;
        } catch (DirectoryNotFoundException) {
            return false;
        }
    }

    public function run(): MigrationResult
    {
        $finder = new Finder();
        $finder->in($this->params->get('kernel.project_dir').'/var/db_backups/');
        $finder->files()->name('*.sql.gz');

        foreach ($finder as $file) {
            $targetFile = $this->params->get('kernel.project_dir').'/var/backups/'.$file->getFilename();
            $this->filesystem->mkdir(\dirname($targetFile));
            $this->filesystem->rename($file->getPathname(), $targetFile, true);
        }

        return $this->createResult(true);
    }
}
