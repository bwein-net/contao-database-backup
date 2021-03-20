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

use Bwein\DatabaseBackup\Service\DatabaseBackupDumper;
use Contao\BackendUser;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\Message;
use Contao\System;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    private $router;
    private $tokenStorage;
    private $translator;
    private $twig;
    private $dumper;

    public function __construct(RouterInterface $router, TokenStorageInterface $tokenStorage, TranslatorInterface $translator, Environment $twig, DatabaseBackupDumper $dumper)
    {
        $this->router = $router;
        $this->tokenStorage = $tokenStorage;
        $this->translator = $translator;
        $this->twig = $twig;
        $this->dumper = $dumper;
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

        if (!empty($createType = $request->get('create'))) {
            return $this->createAction($createType);
        }

        if (!empty($fileName = $request->get('download'))) {
            return $this->downloadAction($fileName, $request->get('backupType'));
        }

        return $this->listAction();
    }

    private function createAction(?string $backupType = null): RedirectResponse
    {
        if ('manual' !== $backupType) {
            Message::addError(
                $this->translator->trans('database_backup_create_not_allowed')
            );
        }

        try {
            $this->dumper->doBackup($backupType);
            Message::addConfirmation(
                $this->translator->trans('database_backup_create_successful')
            );
        } catch (Exception $exception) {
            Message::addError($this->translator->trans($exception->getMessage()));
        }

        return new RedirectResponse($this->router->generate('bwein_contao_database_backup'), 303);
    }

    private function downloadAction(string $fileName, ?string $backupType = null): Response
    {
        if (null !== ($file = $this->dumper->getBackupFile($fileName, $backupType))) {
            $downloadName = null;

            return $this->file($file, $downloadName);
        }

        Message::addError($this->translator->trans('database_backup_not_found'));

        return new RedirectResponse($this->router->generate('bwein_contao_database_backup'), 303);
    }

    private function listAction(): Response
    {
        $parameters = [
            'backUrl' => System::getReferer(),
            'messages' => Message::generate(),
            'backups' => $this->dumper->getBackupFilesList(),
        ];

        return new Response($this->twig->render('@BweinDatabaseBackup/database_backup/index.html.twig', $parameters));
    }
}
