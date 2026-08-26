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

/**
 * Extension points of the Carousel module. Together with the per-slide forms
 * (extensible through TheliaEvents::FORM_AFTER_BUILD . '.carousel_slide') and
 * the Smarty hooks of the back-office templates, these events let other
 * modules add fields, persist them and filter/enrich the front loop WITHOUT
 * forking the module.
 */
final class CarouselEvents
{
    /** Pre-save slide events: carry the model and the full form data (including extra fields). */
    public const SLIDE_CREATE = 'carousel.slide.create';
    public const SLIDE_UPDATE = 'carousel.slide.update';
    public const SLIDE_DELETE = 'carousel.slide.delete';

    /** Post-save slide events: the model has an id and native fields are persisted. */
    public const SLIDE_CREATED = 'carousel.slide.created';
    public const SLIDE_UPDATED = 'carousel.slide.updated';
    public const SLIDE_DELETED = 'carousel.slide.deleted';

    /** Loop extension: add arguments to the carousel loop. */
    public const LOOP_DEFINE_ARGS = 'carousel.loop.define_args';

    /** Loop extension: alter the Propel criteria (front filters, joins…). */
    public const LOOP_EXTEND_CRITERIA = 'carousel.loop.extend_criteria';

    /** Loop extension: add output variables to each loop row. */
    public const LOOP_ENRICH_ROW = 'carousel.loop.enrich_row';

    private function __construct()
    {
    }
}
