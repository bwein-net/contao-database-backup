# Database Backup for Contao Open Source CMS

This Bundle provides a backend module to easily create and list backups of the contao database. The backups can be
downloaded in the backend and backups can be created manually by backend users.

Please read the migration notes from version 1 below!
The console command `bwein:database:backup` and cronjob listener were removed from the extension in version 2 because
Contao 4.13 includes a backup command - see: https://docs.contao.org/manual/en/cli/db-backups/

## Installation

Install the bundle via Composer:

```
composer require bwein-net/contao-database-backup
```


## Configuration

In the ``config/config.yml`` you can add the following optional parameters:

```yaml
# config/config.yml
bwein_database_backup:
    max_backups: 5
    max_days: 14
```

The default of ``max_backups`` is ``7`` backups per type - ``0`` deactivates the automatic purge.

The default of ``max_days`` is ``14`` days type independent - ``0`` deactivates the automatic purge.

## Environment Requirements

- PHP 7.2
- `mysqldump` as global command-line binary
- `gzip` as global command-line binary

## Migration from version 1 to 2

### Add Cronjob

The cronjob listener has been removed so you have to add a manual daily routine:

```
0 4 * * * /path/to/system/vendor/bin/contao-console contao:backup:create
```

see: https://docs.contao.org/manual/en/cli/db-backups/#have-backups-created-automatically

### Replace command usages

The command `bwein:database:backup` has been removed so you need to replace the usages to `contao:backup:create` -
see: https://docs.contao.org/manual/en/cli/db-backups/#contao-backup-create
