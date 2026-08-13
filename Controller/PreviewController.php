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

namespace Carousel\Controller;

use Carousel\Carousel;
use Carousel\Service\CarouselPresenter;
use Carousel\Service\PreviewSettings;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Log\Tlog;

/**
 * Renders a carousel group with the real published slides, as a standalone page
 * meant to be embedded in the back-office preview iframe. When the active theme
 * ships its own carousel component (auto-detected, see PreviewSettings), the
 * preview transparently shows the actual front rendering; otherwise it falls
 * back to the module's default front template.
 */
class PreviewController extends BaseAdminController
{
    #[Route('/admin/module/carousel/preview/{group}', name: 'carousel.preview', methods: ['GET'], requirements: ['group' => Carousel::GROUP_PATTERN])]
    public function preview(string $group, CarouselPresenter $presenter, PreviewSettings $previewSettings): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::VIEW)) {
            return $response;
        }

        $slides = $presenter->publishedSlides($group, $this->getCurrentEditionLocale());

        $component = $previewSettings->component();
        $assetsEntries = $previewSettings->assetsEntries();

        if ($component !== '') {
            $this->ensureFrontAssetsSymlink();

            try {
                return $this->render('carousel/preview', [
                    'group' => $group,
                    'slides' => $slides,
                    'preview_component' => $component,
                    'preview_assets_entries' => $assetsEntries,
                ]);
            } catch (\Throwable $exception) {
                // The resolved component (or an Encore entry) cannot be rendered:
                // fall back to the module default rendering instead of a broken iframe.
                Tlog::getInstance()->addWarning(sprintf(
                    'Carousel preview: rendering with the theme component "%s" failed (%s), falling back to the default rendering.',
                    $component,
                    $exception->getMessage(),
                ));
            }
        }

        return $this->render('carousel/preview', [
            'group' => $group,
            'slides' => $slides,
            'preview_component' => '',
            'preview_assets_entries' => [],
        ]);
    }

    /**
     * The public symlink to the front theme's built assets is normally created
     * by the TwigEngine EncoreExtension, but only during a front request: make
     * sure it exists so the preview iframe's CSS/JS URLs resolve even on a
     * fresh environment that only served admin pages so far.
     */
    private function ensureFrontAssetsSymlink(): void
    {
        try {
            $frontTemplate = $this->getTemplateHelper()->getActiveFrontTemplate();

            if (!$frontTemplate->getAssetsPath()) {
                return;
            }

            $destination = THELIA_WEB_DIR.'templates-assets'.DS.$frontTemplate->getPath().DS.$frontTemplate->getAssetsPath();

            if (is_dir($destination) || !is_dir($frontTemplate->getAbsoluteAssetsPath())) {
                return;
            }

            (new Filesystem())->symlink($frontTemplate->getAbsoluteAssetsPath(), $destination);
        } catch (\Throwable) {
            // Best effort: a missing symlink only degrades the preview styling.
        }
    }
}
