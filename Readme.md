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

## Migration from version 1 to 2

### Replace Configuration

The configuration `bwein_database_backup` has to be replaced by the core configuration:

```yaml
# config/config.yml
contao:
  backup:
    keep_max: 7
    keep_intervals: '14D'
```

see: https://docs.contao.org/manual/en/cli/db-backups/#configuration

### Run Migration

The existing backups will be automatically moved from `var/db_backups` to `var/backups` with a migration by
running `contao:migrate` or use the Contao Install Tool.

### Change backup directory

If you use deploy tools like deployer that define shared dirs, you need to change the backup directory from `var/db_backups` to `var/backups`!

### Add Cronjob

The cronjob listener has been removed so you have to add a manual daily routine:

```
0 4 * * * /path/to/system/vendor/bin/contao-console contao:backup:create
```

see: https://docs.contao.org/manual/en/cli/db-backups/#have-backups-created-automatically

### Replace command usages

The command `bwein:database:backup` has been removed so you need to replace the usages to `contao:backup:create` -
see: https://docs.contao.org/manual/en/cli/db-backups/#contao-backup-create
