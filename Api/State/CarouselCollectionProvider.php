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

namespace Carousel\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Carousel\Api\Resource\Carousel;
use Thelia\Api\Bridge\Propel\State\PropelCollectionProvider;

/**
 * Forces the `published` filter on the public front collection so anonymous
 * callers can never see disabled, scheduled or expired slides — regardless of
 * the query parameters they send. The admin collection keeps `published`
 * optional (it is already behind authentication).
 */
final readonly class CarouselCollectionProvider implements ProviderInterface
{
    public function __construct(private PropelCollectionProvider $decorated)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation->getName() === Carousel::ROUTE_FRONT_GET_COLLECTION) {
            $context['filters']['published'] = '1';
        }

        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
