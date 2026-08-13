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

namespace Carousel\Api\Extension;

use ApiPlatform\Metadata\Operation;
use Carousel\Api\Resource\Carousel;
use Carousel\Model\CarouselQuery;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Thelia\Api\Bridge\Propel\Extension\QueryCollectionExtensionInterface;
use Thelia\Api\Bridge\Propel\Extension\QueryItemExtensionInterface;

/**
 * Restricts the public front endpoints (collection and item) to published
 * slides, unconditionally and server-side — anonymous callers can never reach a
 * disabled, scheduled or expired slide, whatever query parameters or id they send.
 * The admin endpoints are untouched (already behind authentication).
 */
#[AutoconfigureTag('thelia.api.propel.query_extension.collection')]
#[AutoconfigureTag('thelia.api.propel.query_extension.item')]
final readonly class CarouselPublishedExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->apply($query, $resourceClass, $operation);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = [])
    {
        $this->apply($query, $resourceClass, $operation);
    }

    private function apply(ModelCriteria $query, string $resourceClass, ?Operation $operation): void
    {
        if (
            $resourceClass === Carousel::class
            && $query instanceof CarouselQuery
            && \in_array($operation?->getName(), [Carousel::ROUTE_FRONT_GET_COLLECTION, Carousel::ROUTE_FRONT_GET], true)
        ) {
            $query->filterByPublished();
        }
    }
}
