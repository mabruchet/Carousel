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

use Carousel\Model\Carousel as CarouselModel;
use Carousel\Model\CarouselQuery;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;

final readonly class CarouselPresenter
{
    /** Ratio-preserving variant widths used to build the `srcset` attributes. */
    public const DESKTOP_SRCSET_WIDTHS = [768, 1280, 1920];
    public const MOBILE_SRCSET_WIDTHS = [480, 828];

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private CarouselHtmlSanitizer $htmlSanitizer,
    ) {
    }

    /**
     * @return array<string, array<int, array<string, mixed>>> slides indexed by group name, ordered by position
     */
    public function slidesByGroup(string $locale, ?int $thumbnailWidth = null, ?int $thumbnailHeight = null): array
    {
        $groups = [];

        $slides = CarouselQuery::create()
            ->joinWithI18n($locale)
            ->orderByGroup()
            ->orderByPosition()
            ->find();

        foreach ($slides as $slide) {
            $groups[$slide->getGroup() ?? ''][] = $this->present($slide, $locale, $thumbnailWidth, $thumbnailHeight);
        }

        return $groups;
    }

    /**
     * @return array<int, array<string, mixed>> published slides of the group, ordered by position
     */
    public function publishedSlides(string $group, string $locale, ?int $width = null, ?int $height = null): array
    {
        $slides = [];

        $query = CarouselQuery::create()
            ->joinWithI18n($locale)
            ->filterByGroup($group)
            ->filterByPublished()
            ->orderByPosition();

        foreach ($query->find() as $slide) {
            $slides[] = $this->present($slide, $locale, $width, $height);
        }

        return $slides;
    }

    public function processedImageUrl(?string $file, ?int $width = null, ?int $height = null): ?string
    {
        if ($file === null || $file === '') {
            return null;
        }

        $sourceFilepath = (new \Carousel\Carousel())->getUploadDir().DS.$file;

        if (!is_file($sourceFilepath)) {
            return null;
        }

        $imageEvent = (new ImageEvent())
            ->setSourceFilepath($sourceFilepath)
            ->setCacheSubdirectory('carousel');

        // The cache keys file names on getOptionsHash(), which is EMPTY unless
        // width, height, resize_mode AND background_color are ALL set — colliding
        // every render of a file on one cache entry. Hence the explicit background
        // and the square bound (height = width) on the ratio-preserving branch.
        if ($width !== null && $height !== null) {
            $imageEvent
                ->setWidth($width)
                ->setHeight($height)
                ->setBackgroundColor('ffffff')
                // The event stores the mode as string; the core casts it back to int.
                ->setResizeMode((string) \Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS);
        } elseif ($width !== null || $height !== null) {
            // A single dimension (width-only or height-only) drives a ratio-preserving
            // resize. The square bound (missing side = provided side) keeps a distinct
            // cache hash per size while KEEP_IMAGE_RATIO stays driven by the real side.
            $size = $width ?? $height;
            $imageEvent
                ->setWidth($size)
                ->setHeight($size)
                ->setBackgroundColor('ffffff')
                ->setResizeMode((string) \Thelia\Action\Image::KEEP_IMAGE_RATIO);
        }

        $this->eventDispatcher->dispatch($imageEvent, TheliaEvents::IMAGE_PROCESS);

        return $imageEvent->getFileUrl();
    }

    /**
     * Builds a `srcset` attribute value from one source file: each width produces
     * a ratio-preserving variant through the Thelia image cache.
     *
     * @param int[] $widths
     */
    public function processedSrcset(?string $file, array $widths): ?string
    {
        if ($file === null || $file === '') {
            return null;
        }

        $entries = [];

        foreach ($widths as $width) {
            $url = $this->processedImageUrl($file, $width);

            if ($url !== null) {
                $entries[] = $url.' '.$width.'w';
            }
        }

        return $entries === [] ? null : implode(', ', $entries);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(CarouselModel $slide, string $locale, ?int $width = null, ?int $height = null): array
    {
        $slide->setLocale($locale);

        return [
            'id' => $slide->getId(),
            'group' => $slide->getGroup(),
            'position' => $slide->getPosition(),
            'visible' => !$slide->getDisable(),
            'limited' => (bool) $slide->getLimited(),
            'startDate' => $slide->getStartDate(),
            'endDate' => $slide->getEndDate(),
            'status' => $slide->getPublicationStatus(),
            'url' => $slide->getUrl(),
            'linkTarget' => $slide->getLinkTarget(),
            'title' => $slide->getTitle(),
            'alt' => $slide->getAlt(),
            'chapo' => $slide->getChapo(),
            // Sanitized again at read time so descriptions stored before the
            // write-time sanitizer (e.g. migrated 2.x data) are never rendered raw.
            'description' => $this->htmlSanitizer->sanitize($slide->getDescription()),
            'postscriptum' => $slide->getPostscriptum(),
            'buttonLabel' => $slide->getButtonLabel(),
            'file' => $slide->getFile(),
            'mobileFile' => $slide->getMobileFile(),
            'imageUrl' => $this->processedImageUrl($slide->getFile(), $width, $height),
            'mobileImageUrl' => $this->processedImageUrl($slide->getMobileFile(), $width, $height),
            // srcset variants are only generated for full-size renders (front, preview) —
            // never for the back-office thumbnails, which request an explicit size.
            'imageSrcset' => $width === null && $height === null
                ? $this->processedSrcset($slide->getFile(), self::DESKTOP_SRCSET_WIDTHS)
                : null,
            'mobileImageSrcset' => $width === null && $height === null
                ? $this->processedSrcset($slide->getMobileFile(), self::MOBILE_SRCSET_WIDTHS)
                : null,
        ];
    }

}
