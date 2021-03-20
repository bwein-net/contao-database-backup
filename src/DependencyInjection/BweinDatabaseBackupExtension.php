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

use Bwein\DatabaseBackup\Service\DatabaseBackupDumper;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

/**
 * Class BweinDatabaseBackupExtension.
 */
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

        // Set the parameters as arguments for dumper
        $container->getDefinition(DatabaseBackupDumper::class)
            ->setArgument(0, $mergedConfig['max_backups'])
            ->setArgument(1, $mergedConfig['max_days'])
            ;
    }
}
