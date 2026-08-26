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

namespace Carousel\Event\Loop;

use Carousel\Model\Carousel;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Template\Element\LoopResultRow;

/** Lets other modules add output variables to each carousel loop row. */
class CarouselLoopRowEvent extends ActionEvent
{
    public function __construct(
        protected LoopResultRow $row,
        protected Carousel $slide,
        protected bool $backendContext = false,
    ) {
    }

    public function getRow(): LoopResultRow
    {
        return $this->row;
    }

    public function getSlide(): Carousel
    {
        return $this->slide;
    }

    public function isBackendContext(): bool
    {
        return $this->backendContext;
    }
}
