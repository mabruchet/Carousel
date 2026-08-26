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

use Thelia\Core\Event\ActionEvent;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

/** Lets other modules declare extra arguments on the carousel loop. */
class CarouselLoopArgumentEvent extends ActionEvent
{
    public function __construct(protected ArgumentCollection $arguments)
    {
    }

    public function getArguments(): ArgumentCollection
    {
        return $this->arguments;
    }
}
