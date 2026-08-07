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

namespace Carousel\Controller;

use Carousel\Service\CarouselPresenter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;

/**
 * Renders the module's default front template with the real published slides
 * of a group, as a standalone page meant to be embedded in the back-office
 * preview iframe. A theme override of the template is not reflected here.
 */
class PreviewController extends BaseAdminController
{
    #[Route('/admin/module/carousel/preview/{group}', name: 'carousel.preview', methods: ['GET'])]
    public function preview(string $group, CarouselPresenter $presenter): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::VIEW)) {
            return $response;
        }

        return $this->render('carousel/preview', [
            'group' => $group,
            'slides' => $presenter->publishedSlides($group, $this->getCurrentEditionLocale()),
        ]);
    }
}
