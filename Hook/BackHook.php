<?php

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
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Tools\URL;

/**
 * Class BackHook.
 *
 * @author Emmanuel Nurit <enurit@openstudio.fr>
 */
class BackHook extends BaseHook
{
    private const ADMIN_BASE_PATH = '/admin/module/Carousel';

    /**
     * Add a new entry in the admin tools menu.
     *
     * should add to event a fragment with fields : id,class,url,title
     */
    public function onMainTopMenuTools(HookRenderBlockEvent $event): void
    {
        $request = $this->getRequest();

        // Highlight the entry on every page of the module: the module
        // configuration page (/admin/module/Carousel) and the slide edition
        // pages (/admin/module/carousel/edit/{id}) — hence the case insensitive
        // comparison. The trailing slash on both sides enforces a segment
        // boundary, so an unrelated /admin/module/CarouselXxx does not match.
        $isCarouselPage = null !== $request
            && 0 === stripos($request->getPathInfo().'/', self::ADMIN_BASE_PATH.'/');

        $event->add(
            [
                'id' => 'tools_menu_carousel',
                'class' => $isCarouselPage ? 'active' : '',
                'url' => URL::getInstance()->absoluteUrl(self::ADMIN_BASE_PATH),
                'title' => $this->trans('Edit your carousel', [], Carousel::DOMAIN_NAME),
            ]
        );
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $event->add($this->render('module_configuration.html'));
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' =>
                [
                    'type' => 'back',
                    'method' => 'onModuleConfiguration',
                ],
        ];
    }
}
