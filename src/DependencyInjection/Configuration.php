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

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bwein_database_backup');
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->integerNode('max_backups')
                        ->defaultValue(7)
                    ->end()
                    ->integerNode('max_days')
                        ->defaultValue(14)
                    ->end()
                ->end()
        ;

        return $treeBuilder;
    }
}
