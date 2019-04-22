<?php

/*
 * This file is part of Database Backup for Contao Open Source CMS.
 *
 * (c) bwein.net
 *
 * @license MIT
 */

namespace Bwein\DatabaseBackup\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

/**
 * Class BweinDatabaseBackupExtension.
 */
class BweinDatabaseBackupExtension extends ConfigurableExtension
{
    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return 'bwein_database_backup';
    }

    /**
     * {@inheritdoc}
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('listener.yml');
        $loader->load('services.yml');

        // Set the parameters as arguments for dumper
        if ($container->hasDefinition('bwein.database_backup.dumper')) {
            $container->getDefinition('bwein.database_backup.dumper')
                ->setArgument(0, $mergedConfig['max_backups'])
                ->setArgument(1, $mergedConfig['max_days']);
        }
    }
}
