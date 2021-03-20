<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;
use SlevomatCodingStandard\Sniffs\TypeHints\DisallowArrayTypeHintSyntaxSniff;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;

return static function (ContainerConfigurator $containerConfigurator): void {
    $vendorDir = __DIR__.'/vendor';

    if (!is_dir($vendorDir)) {
        $vendorDir = __DIR__.'/../../vendor';
    }

    $containerConfigurator->import($vendorDir.'/contao/easy-coding-standard/config/default.php');

    $parameters = $containerConfigurator->parameters();
    $parameters->set(Option::SKIP, [
        MethodChainingIndentationFixer::class => [
            '*/DependencyInjection/Configuration.php',
        ],
        DisallowArrayTypeHintSyntaxSniff::class => [
            '*Model.php',
        ],
    ]);

    $parameters->set(Option::EXCLUDE_PATHS, ['*/templates/*']);
    $parameters->set(Option::CACHE_DIRECTORY, sys_get_temp_dir().'/ecs_default_cache_database_backup');

    $services = $containerConfigurator->services();
    $services
        ->set(HeaderCommentFixer::class)
        ->call('configure', [[
            'header' => "This file is part of Database Backup for Contao Open Source CMS.\n\n(c) bwein.net\n\n@license MIT",
        ]])
    ;
};
