<?php

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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\CachedFileEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\LangQuery;

/**
 * Slide CRUD helpers: secure image attachment (server-detected MIME, never the
 * client extension), per-group position renumbering, image cache purge.
 */
class CarouselSlideService
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function create(UploadedFile $file, string $group, string $locale): CarouselModel
    {
        $slide = new CarouselModel();

        $lastPosition = (int) CarouselQuery::create()
            ->filterByGroup($group)
            ->orderByPosition(\Propel\Runtime\ActiveQuery\Criteria::DESC)
            ->select('position')
            ->findOne();

        $slide
            ->setGroup($group)
            ->setPosition($lastPosition + 1)
            ->setDisable(0)
            ->setLimited(0);

        // Initialize every locale so the i18n rows exist (Thelia issue #1005).
        foreach (LangQuery::create()->find() as $lang) {
            $slide->setLocale($lang->getLocale())->setTitle('');
        }
        $slide->setLocale($locale);
        $slide->save();

        $this->attachImage($slide, $file, ImageVariant::Desktop);

        return $slide;
    }

    public function delete(int $slideId): void
    {
        $slide = CarouselQuery::create()->findPk($slideId);

        if ($slide === null) {
            return;
        }

        $group = $slide->getGroup();
        $slide->delete();
        $this->renumberGroup($group);
    }

    public function updatePosition(int $slideId, int $position): void
    {
        $slide = CarouselQuery::create()->findPk($slideId);

        if ($slide === null) {
            return;
        }

        $siblings = CarouselQuery::create()
            ->filterByGroup($slide->getGroup())
            ->filterById($slideId, \Propel\Runtime\ActiveQuery\Criteria::NOT_EQUAL)
            ->orderByPosition()
            ->find()
            ->getData();

        array_splice($siblings, max(0, $position - 1), 0, [$slide]);

        foreach ($siblings as $index => $sibling) {
            if ((int) $sibling->getPosition() !== $index + 1) {
                $sibling->setPosition($index + 1)->save();
            }
        }
    }

    public function toggleVisibility(int $slideId): void
    {
        $slide = CarouselQuery::create()->findPk($slideId);

        if ($slide !== null) {
            $slide->setDisable($slide->getDisable() ? 0 : 1)->save();
        }
    }

    public function attachImage(CarouselModel $slide, UploadedFile $uploadedFile, ImageVariant $variant): void
    {
        $uploadDir = $slide->getUploadDir();
        $filesystem = new Filesystem();

        if (!$filesystem->exists($uploadDir)) {
            $filesystem->mkdir($uploadDir);
        }

        $previousFile = $variant === ImageVariant::Desktop ? $slide->getFile() : $slide->getMobileFile();

        $fileName = $this->buildFileName($slide, $uploadedFile, $variant);
        $filePath = $uploadDir.DS.$fileName;

        $filesystem->rename($uploadedFile->getPathname(), $filePath, true);
        $filesystem->chmod($filePath, 0660);

        // Remove the previous file only when the new name differs: a re-upload of
        // the same source name produces the same deterministic name, and deleting
        // "the old one" would delete the file we just wrote.
        if ($previousFile !== null && $previousFile !== '' && $previousFile !== $fileName) {
            $filesystem->remove($uploadDir.DS.$previousFile);
        }

        if ($variant === ImageVariant::Desktop) {
            $slide->setFile($fileName);
        } else {
            $slide->setMobileFile($fileName);
        }

        $slide->save();

        $this->clearImageCache();
    }

    /**
     * Purges the carousel image cache: Thelia only regenerates a cached variant
     * when the file is missing, so a same-name replacement would keep serving
     * the stale image otherwise.
     */
    public function clearImageCache(): void
    {
        $event = new CachedFileEvent();
        $event->setCacheSubdirectory('carousel');

        $this->eventDispatcher->dispatch($event, TheliaEvents::IMAGE_CLEAR_CACHE);
    }

    protected function renumberGroup(?string $group): void
    {
        $slides = CarouselQuery::create()
            ->filterByGroup($group)
            ->orderByPosition()
            ->find();

        foreach ($slides as $index => $slide) {
            if ((int) $slide->getPosition() !== $index + 1) {
                $slide->setPosition($index + 1)->save();
            }
        }
    }

    protected function buildFileName(CarouselModel $slide, UploadedFile $uploadedFile, ImageVariant $variant): string
    {
        // Derive the stored extension from the server-detected MIME type, never
        // from the client-controlled original name/extension: a ".php" uploaded
        // as an image must never reach the web-accessible upload directory.
        $extension = MimeTypes::getDefault()->getExtensions((string) $uploadedFile->getMimeType())[0] ?? 'bin';

        $baseName = preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo($uploadedFile->getClientOriginalName(), \PATHINFO_FILENAME)));
        $baseName = trim(substr((string) $baseName, 0, 64), '-') ?: 'slide';

        return sprintf('%s-%d-%s.%s', $baseName, $slide->getId(), $variant->value, $extension);
    }
}
