<?php

declare(strict_types=1);

/*
 * This file is part of Database Backup for Contao Open Source CMS.
 *
 * (c) bwein.net
 *
 * @license MIT
 */

namespace Bwein\DatabaseBackup;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class BweinDatabaseBackupBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
