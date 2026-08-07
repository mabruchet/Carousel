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

namespace Carousel\Api\Bridge\Filter;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Filter\AbstractFilter;

/**
 * `?published=1` restricts the collection to slides that are enabled and,
 * when a publication window is set, currently inside that window.
 */
final class CarouselPublishedFilter extends AbstractFilter
{
    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (
            $property !== 'published'
            || null === $value
            || !$this->isPropertyEnabled($property, $resourceClass)
            || !filter_var($value, \FILTER_VALIDATE_BOOLEAN)
        ) {
            return;
        }

        $query
            ->where('(carousel.disable IS NULL OR carousel.disable = 0)')
            ->where('(carousel.limited IS NULL OR carousel.limited = 0 OR (carousel.start_date <= NOW() AND carousel.end_date >= NOW()))');
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'published' => [
                'property' => 'published',
                'type' => 'bool',
                'required' => false,
                'description' => 'Only return slides that are enabled and inside their publication window',
            ],
        ];
    }
}
