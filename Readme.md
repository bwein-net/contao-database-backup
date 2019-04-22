# Database Backup for Contao Open Source CMS

This Bundle provides an console command to do easy backups of the contao database.

The backups can be downloaded in the backend and backups can be created manually by backend users.

Automatic backups are triggered by the daily contao cronjob automator.


## Installation

Install the bundle via Composer:

```
composer require bwein-net/contao-database-backup
```


## Command Usage

You can do backups with the following command:

```
vendor/bin/contao-console bwein:database:backup [backupType] [filename]
```

There are two optional parameters ``backupType`` and ``filename``.
The default ``backupType`` is ``manual`` but you can also use one of the other allowed types: ``auto``, ``deploy``, ``migration``

By the ``backupType`` the dumper decides in which subfolder the backups are stored.
The default backup folder is ``var/db_backups``.

If the filename is empty, the fallback will be ``database_name_Y-m-d_H-i-s``. The file extension has to be empty, because ``.sql.gz`` is always added.

The ``auto`` backup is triggered by the daily contao cronjob automator.


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

- PHP 7.0
- `mysqldump` as global command-line binary
- `gzip` as global command-line binary
