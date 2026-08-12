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

namespace Carousel\Api\Serializer;

use Carousel\Api\Resource\Carousel;
use Carousel\Service\CarouselPresenter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Enriches the Carousel resource with processed image URLs so API consumers
 * never have to know the upload directory or the image cache layout.
 * Optional `width`/`height` query parameters resize both variants.
 */
final readonly class CarouselNormalizer implements NormalizerInterface
{
    public function __construct(
        private CarouselPresenter $presenter,
        private RequestStack $requestStack,
        #[Autowire(service: 'serializer.normalizer.object')]
        private NormalizerInterface $normalizer,
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|\ArrayObject|bool|float|int|string|null
    {
        /** @var Carousel $carousel */
        $carousel = $object;

        $request = $this->requestStack->getCurrentRequest();
        $width = $request?->query->get('width') !== null ? (int) $request->query->get('width') : null;
        $height = $request?->query->get('height') !== null ? (int) $request->query->get('height') : null;

        $carousel
            ->setImageUrl($this->presenter->processedImageUrl($carousel->getFile(), $width, $height))
            ->setMobileImageUrl($this->presenter->processedImageUrl($carousel->getMobileFile(), $width, $height));

        // srcset variants only accompany full-size renders: when the client asks
        // for an explicit size, it gets exactly that size.
        if ($width === null && $height === null) {
            $carousel
                ->setImageSrcset($this->presenter->processedSrcset($carousel->getFile(), CarouselPresenter::DESKTOP_SRCSET_WIDTHS))
                ->setMobileImageSrcset($this->presenter->processedSrcset($carousel->getMobileFile(), CarouselPresenter::MOBILE_SRCSET_WIDTHS));
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        // Every operation on this resource is a read: always enrich.
        return $data instanceof Carousel;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Carousel::class => false,
        ];
    }
}
