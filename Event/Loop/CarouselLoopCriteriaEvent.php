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

use Carousel\Model\CarouselQuery;
use Thelia\Core\Event\ActionEvent;

/**
 * Lets other modules alter the carousel loop criteria (front filters, joins,
 * virtual columns…). $backendContext is true when the loop renders inside the
 * back-office: extenders should usually skip their front filters in that case.
 */
class CarouselLoopCriteriaEvent extends ActionEvent
{
    /** @param array<string, mixed> $loopArguments resolved loop argument values */
    public function __construct(
        protected CarouselQuery $query,
        protected bool $backendContext = false,
        protected array $loopArguments = [],
    ) {
    }

    public function getQuery(): CarouselQuery
    {
        return $this->query;
    }

    public function isBackendContext(): bool
    {
        return $this->backendContext;
    }

    /** @return array<string, mixed> */
    public function getLoopArguments(): array
    {
        return $this->loopArguments;
    }

    public function getLoopArgument(string $name): mixed
    {
        return $this->loopArguments[$name] ?? null;
    }
}
