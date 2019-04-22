<?php

/*
 * This file is part of Database Backup for Contao Open Source CMS.
 *
 * (c) bwein.net
 *
 * @license MIT
 */

namespace Bwein\DatabaseBackup\EventListener;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class NavigationListener
{
    protected $requestStack;
    protected $router;
    protected $translator;
    protected $framework;

    /**
     * NavigationListener constructor.
     *
     * @param RequestStack    $requestStack
     * @param RouterInterface $router
     * @param Translator      $translator
     * @param ContaoFramework $framework
     */
    public function __construct(
        RequestStack $requestStack,
        RouterInterface $router,
        Translator $translator,
        ContaoFramework $framework
    ) {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->translator = $translator;
        $this->framework = $framework;
    }

    /**
     * @param array $modules
     *
     * @return array
     */
    public function onGetUserNavigation(array $modules)
    {
        $request = $this->requestStack->getCurrentRequest();

        $this->framework->initialize();

        /** @var BackendUser $backendUser */
        $backendUser = $this->framework->getAdapter(BackendUser::class)->getInstance();

        if ($backendUser->hasAccess('database_backup', 'modules')) {
            $modules['system']['modules']['database_backup'] = [
                'label' => $this->translator->trans('database_backup_title'),
                'class' => 'navigation database_backup',
                'href' => $this->router->generate('contao_database_backup'),
                'isActive' => 'contao_database_backup' === $request->attributes->get('_route'),
            ];
        }

        return $modules;
    }
}
