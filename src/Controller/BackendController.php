<?php

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
use Contao\CoreBundle\Exception\InternalServerErrorException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use Contao\System;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Twig\Environment;
use Twig_Extensions_Extension_Intl;

class BackendController extends AbstractController
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var DatabaseBackupDumper
     */
    protected $dumper;

    /**
     * @var Connection
     */
    private $db;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var AttributeBagInterface
     */
    private $session;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var BackendUser
     */
    private $user;

    /**
     * BackendController constructor.
     *
     * @param string          $downloadFileNameCurrent
     * @param ContaoFramework $framework
     */
    public function __construct(
        Connection $db,
        RequestStack $requestStack,
        SessionInterface $session,
        RouterInterface $router,
        TokenStorageInterface $tokenStorage,
        TranslatorInterface $translator,
        Environment $twig,
        DatabaseBackupDumper $dumper
    ) {
        $this->db = $db;
        $this->requestStack = $requestStack;
        $this->session = $session->getBag('contao_backend');
        $this->router = $router;
        $this->user = $tokenStorage->getToken()->getUser();
        $this->translator = $translator;
        $this->twig = $twig;
        $this->dumper = $dumper;
    }

    /**
     * @return BinaryFileResponse|RedirectResponse|Response
     */
    public function indexAction()
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new InternalServerErrorException('No request object given.');
        }

        /* @var BackendUser $backendUser */
        if (!$this->user->hasAccess('database_backup', 'modules')) {
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

    /**
     * @param null $backupType
     *
     * @return RedirectResponse
     */
    private function createAction($backupType = null)
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

        return new RedirectResponse($this->router->generate('contao_database_backup'), 303);
    }

    /**
     * @param null $backupType
     *
     * @return BinaryFileResponse|RedirectResponse
     */
    private function downloadAction(string $fileName, $backupType = null)
    {
        if (null !== ($file = $this->dumper->getBackupFile($fileName, $backupType))) {
            $downloadName = null;

            return $this->file($file, $downloadName);
        }

        Message::addError($this->translator->trans('database_backup_not_found'));

        return new RedirectResponse($this->router->generate('contao_database_backup'), 303);
    }

    /**
     * @return Response
     */
    private function listAction()
    {
        $this->twig->addExtension(new Twig_Extensions_Extension_Intl());
        $parameters = [
            'backUrl' => System::getReferer(),
            'messages' => Message::generate(),
            'backups' => $this->dumper->getBackupFilesList(),
        ];

        return new Response($this->twig->render('@BweinDatabaseBackup/database_backup/index.html.twig', $parameters));
    }
}
