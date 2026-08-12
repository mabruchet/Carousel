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

namespace Carousel\Twig\Components;

use Carousel\Service\CarouselPresenter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Base front component rendering a carousel group with the module's neutral
 * markup. Deliberately NOT final: a theme overrides it by extending this class
 * and redeclaring its own #[AsTwigComponent] attribute (name + template), the
 * data logic being inherited — see the Readme, "Twig component & overriding".
 */
#[AsTwigComponent(name: 'Carousel', template: '@CarouselModule/components/Carousel.html.twig')]
class Carousel
{
    public string $group = 'home';

    /** Autoplay interval in milliseconds, 0 disables it. */
    public int $autoplay = 0;

    private ?array $slides = null;

    public function __construct(
        protected readonly CarouselPresenter $presenter,
        protected readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>> published slides of the group
     *                                          (flat CarouselPresenter shape)
     */
    public function getSlides(): array
    {
        return $this->slides ??= $this->presenter->publishedSlides(
            $this->group,
            $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en_US',
        );
    }
}
