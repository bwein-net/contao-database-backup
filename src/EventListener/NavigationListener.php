<?php

declare(strict_types=1);

/*
 * This file is part of Database Backup for Contao Open Source CMS.
 *
 * (c) bwein.net
 *
 * @license MIT
 */

namespace Bwein\DatabaseBackup\EventListener;

use Contao\BackendUser;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsHook('getUserNavigation')]
class NavigationListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(array $modules, bool $showAll): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return $modules;
        }

        $token = $this->tokenStorage->getToken();

        if (null === $token) {
            throw new \RuntimeException('No token provided');
        }

        $user = $token->getUser();

        if (!$user instanceof BackendUser || $user->hasAccess('database_backup', 'modules')) {
            $modules['system']['modules']['database_backup'] = [
                'label' => $this->translator->trans('database_backup_title'),
                'title' => '',
                'class' => 'navigation database_backup',
                'href' => $this->router->generate('bwein_contao_database_backup'),
                'isActive' => 'bwein_contao_database_backup' === $request->attributes->get('_route'),
            ];
        }

        return $modules;
    }
}
