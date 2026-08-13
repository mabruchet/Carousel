<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Carousel\Hook;

use Carousel\Carousel;
use Carousel\Service\CarouselPresenter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Tools\URL;

class BackHook extends BaseHook
{
    public function __construct(
        private readonly CarouselPresenter $presenter,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'main.top-menu-tools' => [
                'type' => 'back',
                'method' => 'onMainTopMenuTools',
            ],
            'module.configuration' => [
                'type' => 'back',
                'method' => 'renderModuleConfiguration',
            ],
            'module.config-js' => [
                'type' => 'back',
                'method' => 'renderModuleConfigJs',
            ],
        ];
    }

    /**
     * Add a new entry in the admin tools menu.
     */
    public function onMainTopMenuTools(HookRenderBlockEvent $event): void
    {
        $event->add(
            [
                'id' => 'tools_menu_carousel',
                'class' => '',
                'url' => URL::getInstance()->absoluteUrl('/admin/module/Carousel'),
                'title' => $this->trans('Edit your carousel', [], Carousel::DOMAIN_NAME),
            ]
        );
    }

    public function renderModuleConfiguration(HookRenderEvent $event): void
    {
        $locale = $this->getRequest()?->getLocale() ?? 'en_US';

        $event->add($this->render('carousel/hook/module-configuration.html.twig', [
            'groups' => $this->presenter->slidesByGroup($locale, 220, 80),
            'status_labels' => $this->statusLabels(),
        ]));
    }

    public function renderModuleConfigJs(HookRenderEvent $event): void
    {
        $event->add($this->render('carousel/hook/module-config-js.html.twig'));
    }

    /**
     * @return array<string, array{label: string, variant: string}>
     */
    private function statusLabels(): array
    {
        return [
            'online' => ['label' => $this->trans('Online', [], Carousel::BO_DOMAIN), 'variant' => 'success'],
            'disabled' => ['label' => $this->trans('Disabled', [], Carousel::BO_DOMAIN), 'variant' => 'secondary'],
            'scheduled' => ['label' => $this->trans('Scheduled', [], Carousel::BO_DOMAIN), 'variant' => 'info'],
            'expired' => ['label' => $this->trans('Expired', [], Carousel::BO_DOMAIN), 'variant' => 'warning'],
        ];
    }
}
