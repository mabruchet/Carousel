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

namespace Carousel\Event;

use Carousel\Model\Carousel;
use Symfony\Component\HttpFoundation\ParameterBag;
use Thelia\Core\Event\ActionEvent;

/**
 * Dispatched around every slide create/update/delete (see CarouselEvents).
 * Listeners get the slide model and the COMPLETE validated form data — form
 * fields added by other modules through FORM_AFTER_BUILD are present in the
 * bag, so extenders can persist them on the post-save events.
 */
class CarouselSlideEvent extends ActionEvent
{
    public function __construct(
        protected Carousel $slide,
        protected ParameterBag $data = new ParameterBag(),
        protected ?string $locale = null,
    ) {
    }

    public function getSlide(): Carousel
    {
        return $this->slide;
    }

    public function setSlide(Carousel $slide): self
    {
        $this->slide = $slide;

        return $this;
    }

    /** Validated form data, including fields added by extender modules. */
    public function getData(): ParameterBag
    {
        return $this->data;
    }

    /** Back-office edition locale used to persist the i18n fields. */
    public function getLocale(): ?string
    {
        return $this->locale;
    }
}
