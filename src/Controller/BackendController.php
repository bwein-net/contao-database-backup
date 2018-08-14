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
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Message;
use Contao\System;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Twig_Environment;
use Twig_Extensions_Extension_Intl;

class BackendController extends Controller
{
    protected $downloadFileNameCurrent;
    protected $requestStack;
    protected $request;
    protected $router;
    protected $translator;
    protected $framework;
    protected $dumper;
    protected $twig;

    /**
     * BackendController constructor.
     *
     * @param string                   $downloadFileNameCurrent
     * @param RequestStack             $requestStack
     * @param RouterInterface          $router
     * @param TranslatorInterface      $translator
     * @param ContaoFrameworkInterface $framework
     * @param DatabaseBackupDumper     $dumper
     * @param Twig_Environment         $twig
     */
    public function __construct(
        string $downloadFileNameCurrent,
        RequestStack $requestStack,
        RouterInterface $router,
        TranslatorInterface $translator,
        ContaoFrameworkInterface $framework,
        DatabaseBackupDumper $dumper,
        Twig_Environment $twig
    ) {
        $this->downloadFileNameCurrent = $downloadFileNameCurrent;
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->translator = $translator;
        $this->framework = $framework;
        $this->dumper = $dumper;
        $this->twig = $twig;
    }

    /**
     * @return BinaryFileResponse|RedirectResponse|Response
     */
    public function indexAction()
    {
        $this->request = $this->requestStack->getCurrentRequest();
        if (null === $this->request) {
            throw new InternalServerErrorException('No request object given.');
        }

        $this->framework->initialize();

        /** @var BackendUser $backendUser */
        $backendUser = $this->framework->getAdapter(BackendUser::class)->getInstance();
        if (!$backendUser->hasAccess('database_backup', 'modules')) {
            throw new AccessDeniedException('Not enough permissions to access database_backup.');
        }

        if (!empty($createType = $this->request->get('create'))) {
            return $this->createAction($createType);
        }
        if (!empty($fileName = $this->request->get('download'))) {
            return $this->downloadAction($fileName, $this->request->get('backupType'));
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
        } catch (\Exception $exception) {
            Message::addError($this->translator->trans($exception->getMessage()));
        }

        return new RedirectResponse($this->router->generate('contao_database_backup'), 303);
    }

    /**
     * @param string $fileName
     * @param null   $backupType
     *
     * @return BinaryFileResponse|RedirectResponse
     */
    private function downloadAction(string $fileName, $backupType = null)
    {
        if (null !== ($file = $this->dumper->getBackupFile($fileName, $backupType))) {
            $downloadName = null;
            if (empty($backupType) && !empty($this->downloadFileNameCurrent)) {
                $downloadName = $this->downloadFileNameCurrent.$this->dumper::DEFAULT_EXTENSION;
            }

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
            'backupTypes' => $this->dumper->getBackupTypesFilesList(),
        ];

        return new Response($this->twig->render('@BweinDatabaseBackup/database_backup/index.html.twig', $parameters));
    }
}
