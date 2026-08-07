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
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    /**
     * @return array<string, array<int, array<string, mixed>>> slides indexed by group name, ordered by position
     */
    public function slidesByGroup(string $locale, ?int $thumbnailWidth = null, ?int $thumbnailHeight = null): array
    {
        $groups = [];

        foreach (CarouselQuery::create()->orderByGroup()->orderByPosition()->find() as $slide) {
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

        if ($width !== null && $height !== null) {
            $imageEvent
                ->setWidth($width)
                ->setHeight($height)
                // The event stores the mode as string; the core casts it back to int.
                ->setResizeMode((string) \Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS);
        }

        $this->eventDispatcher->dispatch($imageEvent, TheliaEvents::IMAGE_PROCESS);

        return $imageEvent->getFileUrl();
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
            'status' => $this->publicationStatus($slide),
            'url' => $slide->getUrl(),
            'linkTarget' => $slide->getLinkTarget(),
            'title' => $slide->getTitle(),
            'alt' => $slide->getAlt(),
            'chapo' => $slide->getChapo(),
            'description' => $slide->getDescription(),
            'postscriptum' => $slide->getPostscriptum(),
            'buttonLabel' => $slide->getButtonLabel(),
            'file' => $slide->getFile(),
            'mobileFile' => $slide->getMobileFile(),
            'imageUrl' => $this->processedImageUrl($slide->getFile(), $width, $height),
            'mobileImageUrl' => $this->processedImageUrl($slide->getMobileFile(), $width, $height),
        ];
    }

    /**
     * @return 'online'|'disabled'|'scheduled'|'expired'
     */
    private function publicationStatus(CarouselModel $slide): string
    {
        if ($slide->getDisable()) {
            return 'disabled';
        }

        if (!$slide->getLimited()) {
            return 'online';
        }

        $now = new \DateTime();

        if ($slide->getStartDate() !== null && $now < $slide->getStartDate()) {
            return 'scheduled';
        }

        if ($slide->getEndDate() !== null && $now > $slide->getEndDate()) {
            return 'expired';
        }

        return 'online';
    }
}
