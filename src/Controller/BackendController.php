<?php

declare(strict_types=1);

/*
 * This file is part of Database Backup for Contao Open Source CMS.
 *
 * (c) bwein.net
 *
 * @license MIT
 */

namespace Bwein\DatabaseBackup\Controller;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Doctrine\Backup\BackupManager;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use Contao\System;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\ServiceAnnotationBundle\Annotation\ServiceTag;
use Twig\Environment;

/**
 * @Route("/contao/database_backup", name="bwein_contao_database_backup", defaults={"_scope": "backend"})
 * @ServiceTag("controller.service_arguments")
 */
class BackendController extends AbstractController
{
    private RouterInterface $router;
    private TokenStorageInterface $tokenStorage;
    private TranslatorInterface $translator;
    private Environment $twig;
    private ContaoFramework $framework;
    private BackupManager $backupManager;

    public function __construct(RouterInterface $router, TokenStorageInterface $tokenStorage, TranslatorInterface $translator, Environment $twig, ContaoFramework $framework, BackupManager $backupManager)
    {
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
        $this->translator = $translator;
        $this->twig = $twig;
        $this->framework = $framework;
        $this->backupManager = $backupManager;
    }

    public function __invoke(Request $request): Response
    {
        $token = $this->tokenStorage->getToken();

        if (null === $token) {
            throw new \RuntimeException('No token provided');
        }

        $user = $token->getUser();

        if (!$user instanceof BackendUser || !$user->hasAccess('database_backup', 'modules')) {
            throw new AccessDeniedException('Not enough permissions to access database_backup.');
        }

        $this->framework->initialize();

        if (!empty($createType = $request->get('create'))) {
            return $this->createAction($createType);
        }

        if (!empty($fileName = $request->get('download'))) {
            return $this->downloadAction($fileName);
        }

        return $this->listAction();
    }

    private function createAction(string $createType = null): RedirectResponse
    {
        if ('manual' !== $createType) {
            Message::addError(
                $this->translator->trans('database_backup_create_not_allowed')
            );
        }

        $config = $this->backupManager->createCreateConfig();

        try {
            $this->backupManager->create($config);
            Message::addConfirmation(
                $this->translator->trans('database_backup_create_successful')
            );
        } catch (\Exception $exception) {
            Message::addError($this->translator->trans($exception->getMessage()));
        }

        return new RedirectResponse($this->router->generate('bwein_contao_database_backup'), 303);
    }

    /**
     * @return StreamedResponse|RedirectResponse
     */
    private function downloadAction(string $fileName)
    {
        $backup = $this->backupManager->getBackupByName($fileName);

        if (null !== $backup) {
            $response = new StreamedResponse(
                function () use ($backup): void {
                    $outputStream = fopen('php://output', 'w');
                    stream_copy_to_stream($this->backupManager->readStream($backup), $outputStream);
                }
            );
            $dispositionHeader = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName);
            $response->headers->set('Content-Disposition', $dispositionHeader);

            return $response;
        }

        Message::addError($this->translator->trans('database_backup_not_found'));

        return new RedirectResponse($this->router->generate('bwein_contao_database_backup'), 303);
    }

    private function listAction(): Response
    {
        System::loadLanguageFile('default');
        $backups = $this->backupManager->listBackups();
        $timeZone = new \DateTimeZone(date_default_timezone_get());

        $parameters = [
            'backUrl' => System::getReferer(),
            'messages' => Message::generate(),
            'backups' => $this->formatForTable($backups, $timeZone),
        ];

        return new Response($this->twig->render('@BweinDatabaseBackup/database_backup/index.html.twig', $parameters));
    }

    private function formatForTable(array $backups, \DateTimeZone $timeZone): array
    {
        $formatted = [];

        foreach ($backups as $backup) {
            // TODO: Change this to \DateTime::createFromInterface($backup->getCreatedAt()) as soon as we require PHP >=8.0
            $localeDateTime = new \DateTime('@'.$backup->getCreatedAt()->getTimestamp(), $backup->getCreatedAt()->getTimezone());
            $localeDateTime->setTimezone($timeZone);

            $formatted[] = [
                'dateTimeRaw' => $backup->getCreatedAt()->getTimestamp(),
                'dateTime' => $localeDateTime->format(Config::get('datimFormat')),
                'sizeRaw' => $backup->getSize(),
                'size' => System::getReadableSize($backup->getSize()),
                'fileName' => $backup->getFilename(),
            ];
        }

        return $formatted;
    }
}
