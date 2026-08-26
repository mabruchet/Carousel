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

namespace Carousel\EventListener;

use Carousel\Event\CarouselEvents;
use Carousel\Event\CarouselSlideEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Persists the NATIVE fields of a slide on SLIDE_UPDATE. Extender modules
 * persist their own fields by listening to SLIDE_UPDATED / SLIDE_CREATED
 * (post-save, the slide id is available and native data is stored).
 */
class CarouselSlideListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CarouselEvents::SLIDE_UPDATE => ['updateNativeFields', 128],
        ];
    }

    public function updateNativeFields(CarouselSlideEvent $event): void
    {
        $slide = $event->getSlide();
        $data = $event->getData();

        $slide
            ->setUrl($data->get('url'))
            ->setLinkTarget($data->get('link_target'))
            ->setGroup($data->get('group'))
            ->setDisable($data->getBoolean('disable') ? 1 : 0)
            ->setLimited($data->getBoolean('limited') ? 1 : 0)
            ->setStartDate($data->get('start_date'))
            ->setEndDate($data->get('end_date'));

        if (null !== $locale = $event->getLocale()) {
            $slide
                ->setLocale($locale)
                ->setTitle($data->get('title'))
                ->setAlt($data->get('alt'))
                ->setChapo($data->get('chapo'))
                ->setDescription($data->get('description'))
                ->setPostscriptum($data->get('postscriptum'))
                ->setButtonLabel($data->get('button_label'));
        }

        $slide->save();
    }
}
