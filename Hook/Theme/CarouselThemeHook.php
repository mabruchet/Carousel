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

namespace Carousel\Hook\Theme;

use Carousel\Service\CarouselPresenter;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

/**
 * Answers `theme_hook('carousel', { group: 'home' })` — or the shorthand
 * `theme_hook('carousel.home')` — with the default carousel markup.
 */
final readonly class CarouselThemeHook implements ThemeHookInterface
{
    public function __construct(
        private CarouselPresenter $presenter,
        private RequestStack $requestStack,
        private Environment $twig,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return $hookName === 'carousel' || str_starts_with($hookName, 'carousel.');
    }

    public function render(string $hookName, array $parameters): string
    {
        $group = (string) ($parameters['group'] ?? (str_contains($hookName, '.') ? substr($hookName, \strlen('carousel.')) : 'home'));
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en_US';

        $slides = $this->presenter->publishedSlides($group, $locale);

        if ($slides === []) {
            return '';
        }

        return $this->twig->render('@CarouselModule/theme-hook/carousel.html.twig', [
            'group' => $group,
            'slides' => $slides,
            'autoplay' => (int) ($parameters['autoplay'] ?? 0),
        ]);
    }
}
