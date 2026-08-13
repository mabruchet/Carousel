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

namespace Carousel\Service;

use Carousel\Carousel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * Resolves how the back-office preview should render, transparently for the
 * administrator: the theme component is auto-detected (any registered Twig
 * component extending the module's base component — the documented override
 * contract) and the theme's "app" Encore entry is used when it exists. Both
 * can still be forced through module config values (developer override, no UI):
 * Carousel::CONFIG_PREVIEW_COMPONENT / Carousel::CONFIG_PREVIEW_ASSETS_ENTRIES.
 */
final readonly class PreviewSettings
{
    /**
     * @param list<string> $themeComponents collected by PreviewComponentPass
     */
    public function __construct(
        #[Autowire(param: 'carousel.preview.theme_components')]
        private array $themeComponents,
        private TemplateHelperInterface $templateHelper,
    ) {
    }

    /** The component to render in the preview, '' for the module default rendering. */
    public function component(): string
    {
        $override = trim((string) Carousel::getConfigValue(Carousel::CONFIG_PREVIEW_COMPONENT, ''));

        if ($override !== '') {
            return $override;
        }

        return $this->themeComponents[0] ?? '';
    }

    /**
     * @return list<string> Encore entries to load in the preview iframe
     */
    public function assetsEntries(): array
    {
        $override = trim((string) Carousel::getConfigValue(Carousel::CONFIG_PREVIEW_ASSETS_ENTRIES, ''));

        if ($override !== '') {
            return array_values(array_filter(array_map(trim(...), explode(',', $override))));
        }

        // Convention: the front theme's main Encore entry is named "app".
        try {
            $entrypoints = $this->templateHelper->getActiveFrontTemplate()->getAbsoluteAssetsPath().DS.'entrypoints.json';

            if (is_file($entrypoints)) {
                $manifest = json_decode((string) file_get_contents($entrypoints), true);

                if (isset($manifest['entrypoints']['app'])) {
                    return ['app'];
                }
            }
        } catch (\Throwable) {
            // No resolvable front template assets: preview without theme assets.
        }

        return [];
    }
}
