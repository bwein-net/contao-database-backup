<?php

declare(strict_types=1);

/*
 * This file is part of Database Backup for Contao Open Source CMS.
 *
 * (c) bwein.net
 *
 * @license MIT
 */

namespace Bwein\DatabaseBackup\DependencyInjection;

use Bwein\DatabaseBackup\Cron\BackupCron;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class BweinDatabaseBackupExtension extends ConfigurableExtension
{
    public function getAlias(): string
    {
        return 'bwein_database_backup';
    }

    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        if (null !== $mergedConfig['cron_interval']) {
            $container->getDefinition(BackupCron::class)->addTag(
                'contao.cronjob',
                ['interval' => $mergedConfig['cron_interval']]
            );
        }
    }
}
