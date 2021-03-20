<?php

declare(strict_types=1);

/*
 * This file is part of Database Backup for Contao Open Source CMS.
 *
 * (c) bwein.net
 *
 * @license MIT
 */

namespace Bwein\DatabaseBackup\Service;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\System;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DatabaseBackupDumper
{
    public const BACKUPS_PATH = \DIRECTORY_SEPARATOR.'var'.\DIRECTORY_SEPARATOR.'db_backups';
    public const DEFAULT_EXTENSION = '.sql.gz';
    /**
     * @deprecated no longer created but to remove created symlink
     */
    public const CURRENT_BACKUP = 'current'.self::DEFAULT_EXTENSION;

    public const TYPE_MANUAL = 'manual';
    public const TYPE_AUTO = 'auto';
    public const TYPE_DEPLOY = 'deploy';
    public const TYPE_MIGRATION = 'migration';

    public const DIRECTORY_FOR_MANUAL_BACKUP = 'manual';
    public const DIRECTORY_FOR_AUTO_BACKUP = 'auto';
    public const DIRECTORY_FOR_DEPLOY_BACKUP = 'deploy';
    public const DIRECTORY_FOR_MIGRATION_BACKUP = 'migration';

    private $maxBackups;
    private $maxDays;
    private $databaseHost;
    private $databasePort;
    private $databaseName;
    private $databaseUser;
    private $databasePassword;
    private $backupsPath;
    private $fs;
    private $logger;
    private $framework;
    private $backupFileName;
    private $backupType;
    private $backupTypePath;

    private $output;
    private static $backupTypesPaths = [
        self::TYPE_MANUAL => self::DIRECTORY_FOR_MANUAL_BACKUP,
        self::TYPE_AUTO => self::DIRECTORY_FOR_AUTO_BACKUP,
        self::TYPE_DEPLOY => self::DIRECTORY_FOR_DEPLOY_BACKUP,
        self::TYPE_MIGRATION => self::DIRECTORY_FOR_MIGRATION_BACKUP,
    ];

    public function __construct(?int $maxBackups, ?int $maxDays, ParameterBagInterface $params, ContaoFramework $framework, LoggerInterface $logger, Filesystem $fs = null)
    {
        $this->maxBackups = $maxBackups ?? 0;
        $this->maxDays = $maxDays ?? 0;
        $this->databaseHost = $params->get('database_host');
        $this->databasePort = $params->get('database_port');
        $this->databaseName = $params->get('database_name');
        $this->databaseUser = $params->get('database_user');
        $this->databasePassword = $params->get('database_password');
        $this->backupsPath = $params->get('kernel.project_dir').static::BACKUPS_PATH;
        $this->framework = $framework;
        $this->logger = $logger;
        $this->fs = $fs ?: new Filesystem();
    }

    public static function getBackupTypes(): array
    {
        return array_keys(static::$backupTypesPaths);
    }

    public function doBackup(string $backupType = null, string $filename = null, OutputInterface $output = null): bool
    {
        $this->output = $output;

        if (null === $this->output) {
            $this->output = new NullOutput();
        }

        try {
            $this->framework->initialize();
        } catch (Exception $exception) {
            $this->output->writeln('<comment>Framework was not initialized!</comment>');

            return false;
        }

        if (empty($this->databaseName)) {
            throw new InvalidArgumentException('databaseName is not defined.');
        }

        $this->backupType = $backupType;
        $this->backupTypePath = $this->resolveBackupTypePath();

        $this->backupFileName = $filename;

        if (empty($this->backupFileName)) {
            $this->backupFileName = $this->databaseName.'_'.date('Y-m-d_H-i-s');
        }
        $this->backupFileName .= '.sql.gz';

        $this->output->writeln(
            sprintf(
                '<comment>Database backup of "%s" to "%s/%s" starting...</comment>',
                $this->databaseName,
                $this->backupTypePath,
                $this->backupFileName
            )
        );

        $this->validateBackupPath();
        $this->dumpDatabase();
        $this->logDump();

        $this->removeMaxCountOldest();
        $this->removeMaxDays();

        return true;
    }

    public function getBackupFilesList(): array
    {
        $this->validateBackupPath();
        $this->removeMaxDays();

        $finder = new Finder();
        $finder->in($this->backupsPath);
        $finder->files()->name('*'.static::DEFAULT_EXTENSION);
        $finder->sort(
            static function (SplFileInfo $a, SplFileInfo $b) {
                return $b->getMTime() - $a->getMTime();
            }
        );

        if (!$finder->hasResults()) {
            return [];
        }

        $this->framework->initialize();
        System::loadLanguageFile('default');

        $return = [];

        foreach ($finder as $file) {
            $return[] = [
                'dateTimeRaw' => $file->getMTime(),
                'dateTime' => Date::parse(Config::get('datimFormat'), $file->getMTime()),
                'sizeRaw' => $file->getSize(),
                'size' => System::getReadableSize($file->getSize()),
                'type' => $file->getRelativePath(),
                'filePath' => $file->getRelativePathname(),
                'fileName' => $file->getFilename(),
            ];
        }

        return $return;
    }

    public function getBackupFile(string $fileName, ?string $backupType = null): ?File
    {
        $backupTypePath = '';

        if (!empty($backupType)) {
            $backupTypePath = $this->resolveBackupTypePath($backupType).\DIRECTORY_SEPARATOR;
        }
        $filePath = $this->backupsPath.\DIRECTORY_SEPARATOR.$backupTypePath.$fileName;

        if (!$this->fs->exists($filePath)) {
            return null;
        }

        return new File($filePath);
    }

    private function resolveBackupTypePath(?string $backupType = null): string
    {
        if (empty($backupType)) {
            if (empty($this->backupType)) {
                $this->backupType = static::TYPE_MANUAL;
            }

            $backupType = $this->backupType;
        }

        if (!\in_array($backupType, static::getBackupTypes(), true)) {
            throw new \InvalidArgumentException(sprintf('Unknown backup type %s. Allowed parameters are: %s', $backupType, implode(',', static::getBackupTypes())));
        }

        return static::$backupTypesPaths[$backupType];
    }

    private function validateBackupPath(string $backupTypePath = ''): void
    {
        if (empty($backupTypePath)) {
            $backupTypePath = $this->backupTypePath;
        }
        $this->fs->mkdir($this->backupsPath.\DIRECTORY_SEPARATOR.$backupTypePath);
        $this->fs->remove($this->backupsPath.\DIRECTORY_SEPARATOR.static::CURRENT_BACKUP);
    }

    private function dumpDatabase(): void
    {
        $dumpCommand =
            [
                'mysqldump',
                $this->databaseName,
                '--host='.$this->databaseHost,
                '--port='.$this->databasePort,
                '--user='.$this->databaseUser,
                '--password='.$this->databasePassword,
                '--add-locks',
                '--add-drop-table',
                '| gzip -c',
            ];

        $cmd = implode(' ', $dumpCommand);
        $process = Process::fromShellCommandline($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->writeDumpFile($process->getOutput());
    }

    private function writeDumpFile($content): void
    {
        $this->fs->dumpFile(
            $this->backupsPath.\DIRECTORY_SEPARATOR.$this->backupTypePath.\DIRECTORY_SEPARATOR.$this->backupFileName,
            $content
        );
    }

    private function logDump(): void
    {
        $message = sprintf(
            'Database backup of "%s" to "%s/%s" successfully done.',
            $this->databaseName,
            $this->backupTypePath,
            $this->backupFileName
        );
        $this->logger->info($message, ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]);
        $this->output->writeln('<info>'.$message.'</info>');
    }

    private function removeMaxCountOldest(): void
    {
        if (0 === $this->maxBackups) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($this->backupsPath.\DIRECTORY_SEPARATOR.$this->backupTypePath)->sort(
            static function (SplFileInfo $a, SplFileInfo $b) {
                return $b->getMTime() - $a->getMTime();
            }
        );

        $count = 0;

        foreach ($finder as $file) {
            ++$count;

            if ($count > $this->maxBackups) {
                $this->fs->remove($file);

                $message = sprintf(
                    'Database backup "%s/%s" removed (max %d backups).',
                    $this->backupTypePath,
                    $file->getFilename(),
                    $this->maxBackups
                );
                $this->logger->info($message, ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]);
                $this->output->writeln('<comment>'.$message.'</comment>');
            }
        }
    }

    private function removeMaxDays(): void
    {
        if (0 === $this->maxDays) {
            return;
        }

        if (null === $this->output) {
            $this->output = new NullOutput();
        }

        $finder = new Finder();
        $finder->in($this->backupsPath);
        $finder->files()->name('*'.static::DEFAULT_EXTENSION);

        foreach ($finder as $file) {
            if ((time() - $file->getMTime()) / (60 * 60 * 24) > $this->maxDays) {
                $this->fs->remove($file);

                $message = sprintf(
                    'Database backup "%s" removed (max %d days).',
                    $file->getRelativePathname(),
                    $this->maxDays
                );
                $this->logger->info($message, ['contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)]);
                $this->output->writeln('<comment>'.$message.'</comment>');
            }
        }
    }
}
