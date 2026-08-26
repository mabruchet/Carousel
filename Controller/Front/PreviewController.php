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

namespace Carousel\Controller\Front;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;

/**
 * Back-office preview of a carousel group, served from the FRONT so that the
 * iframe of the configuration page shows the real rendering of the shop.
 *
 * Being a front controller, render() resolves `carousel-preview` in the ACTIVE
 * FRONT THEME first, and only falls back to the module's own
 * templates/frontOffice/default/carousel-preview.html when the theme provides
 * no override. A theme can therefore ship its own carousel markup (splide,
 * responsive images, ...) and the back-office preview follows automatically.
 *
 * The page is not public: it requires an authenticated admin session, which is
 * readable from the front through the shared security context.
 */
class PreviewController extends BaseFrontController
{
    public function preview(Request $request): Response
    {
        $group = (string) $request->get('group');

        if (!$this->getSecurityContext()->hasAdminUser()) {
            return new Response('Forbidden', Response::HTTP_FORBIDDEN);
        }

        return $this->render('carousel-preview', [
            'group' => $group,
        ]);
    }
}
