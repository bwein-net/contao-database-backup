<?php

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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DatabaseBackupDumper
{
    const BACKUPS_PATH = \DIRECTORY_SEPARATOR.'var'.\DIRECTORY_SEPARATOR.'db_backups';
    const DEFAULT_EXTENSION = '.sql.gz';
    /** @deprecated no longer created but to remove created symlink */
    const CURRENT_BACKUP = 'current'.self::DEFAULT_EXTENSION;

    const TYPE_MANUAL = 'manual';
    const TYPE_AUTO = 'auto';
    const TYPE_DEPLOY = 'deploy';
    const TYPE_MIGRATION = 'migration';

    const DIRECTORY_FOR_MANUAL_BACKUP = 'manual';
    const DIRECTORY_FOR_AUTO_BACKUP = 'auto';
    const DIRECTORY_FOR_DEPLOY_BACKUP = 'deploy';
    const DIRECTORY_FOR_MIGRATION_BACKUP = 'migration';

    protected $maxBackups;
    protected $maxDays;
    protected $databaseHost;
    protected $databasePort;
    protected $databaseName;
    protected $databaseUser;
    protected $databasePassword;
    protected $backupsPath;
    protected $fs;
    protected $logger;
    protected $framework;
    protected $backupFileName;
    protected $backupType;
    protected $backupTypePath;

    /**
     * @var OutputInterface|null
     */
    protected $output;

    /**
     * @var array
     */
    private static $backupTypesPaths = [
        self::TYPE_MANUAL => self::DIRECTORY_FOR_MANUAL_BACKUP,
        self::TYPE_AUTO => self::DIRECTORY_FOR_AUTO_BACKUP,
        self::TYPE_DEPLOY => self::DIRECTORY_FOR_DEPLOY_BACKUP,
        self::TYPE_MIGRATION => self::DIRECTORY_FOR_MIGRATION_BACKUP,
    ];

    /**
     * DatabaseBackupDumper constructor.
     *
     * @param int             $maxBackups
     * @param int             $maxDays
     * @param string|null     $databaseHost
     * @param int|null        $databasePort
     * @param string|null     $databaseName
     * @param string|null     $databaseUser
     * @param string|null     $databasePassword
     * @param string          $rootDir
     * @param ContaoFramework $framework
     * @param LoggerInterface $logger
     * @param Filesystem|null $fs
     */
    public function __construct(
        int $maxBackups,
        int $maxDays,
        $databaseHost,
        $databasePort,
        $databaseName,
        $databaseUser,
        $databasePassword,
        string $rootDir,
        ContaoFramework $framework,
        LoggerInterface $logger,
        Filesystem $fs = null
    ) {
        $this->maxBackups = $maxBackups;
        $this->maxDays = $maxDays;
        $this->databaseHost = $databaseHost;
        $this->databasePort = $databasePort;
        $this->databaseName = $databaseName;
        $this->databaseUser = $databaseUser;
        $this->databasePassword = $databasePassword;
        $this->backupsPath = $rootDir.static::BACKUPS_PATH;
        $this->framework = $framework;
        $this->logger = $logger;
        $this->fs = $fs ? $fs : new Filesystem();
    }

    /**
     * @return array
     */
    public static function getBackupTypes()
    {
        return array_keys(static::$backupTypesPaths);
    }

    /**
     * @param string|null          $backupType
     * @param string|null          $filename
     * @param OutputInterface|null $output
     *
     * @return bool
     */
    public function doBackup(string $backupType = null, string $filename = null, OutputInterface $output = null)
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
        $this->backupFileName = $this->backupFileName.'.sql.gz';

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

    /**
     * @return array
     */
    public function getBackupFilesList()
    {
        $this->validateBackupPath();
        $this->removeMaxDays();

        $finder = new Finder();
        $finder->in($this->backupsPath);
        $finder->files()->name('*'.static::DEFAULT_EXTENSION);
        $finder->sort(
            function (SplFileInfo $a, SplFileInfo $b) {
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

    /**
     * @param string $fileName
     * @param null   $backupType
     *
     * @return File|null
     */
    public function getBackupFile(string $fileName, $backupType = null)
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

    /**
     * @param string $backupType
     *
     * @return string
     */
    private function resolveBackupTypePath(string $backupType = '')
    {
        if (empty($backupType)) {
            if (empty($this->backupType)) {
                $this->backupType = static::TYPE_MANUAL;
            }

            $backupType = $this->backupType;
        }

        if (!\in_array($backupType, static::getBackupTypes(), true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unknown backup type %s. Allowed parameters are: %s',
                    $backupType,
                    implode(',', static::getBackupTypes())
                )
            );
        }

        $path = (string) static::$backupTypesPaths[$backupType];

        return $path;
    }

    private function validateBackupPath(string $backupTypePath = '')
    {
        if (empty($backupTypePath)) {
            $backupTypePath = $this->backupTypePath;
        }
        $this->fs->mkdir($this->backupsPath.\DIRECTORY_SEPARATOR.$backupTypePath);
        $this->fs->remove($this->backupsPath.\DIRECTORY_SEPARATOR.static::CURRENT_BACKUP);
    }

    private function dumpDatabase()
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
        $process = \method_exists(Process::class, 'fromShellCommandline') ? Process::fromShellCommandline(
            $cmd
        ) : new Process($cmd);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->writeDumpFile($process->getOutput());
    }

    private function writeDumpFile($content)
    {
        $this->fs->dumpFile(
            $this->backupsPath.\DIRECTORY_SEPARATOR.$this->backupTypePath.\DIRECTORY_SEPARATOR.$this->backupFileName,
            $content
        );
    }

    private function logDump()
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

    private function removeMaxCountOldest()
    {
        if (0 === $this->maxBackups) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($this->backupsPath.\DIRECTORY_SEPARATOR.$this->backupTypePath)->sort(
            function (SplFileInfo $a, SplFileInfo $b) {
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

    private function removeMaxDays()
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
