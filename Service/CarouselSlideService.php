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
use Carousel\Model\Map\CarouselTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Thelia\Core\Event\CachedFileEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\File\FileManager;

final readonly class CarouselSlideService
{
    public function __construct(
        private FileManager $fileManager,
        private CarouselHtmlSanitizer $htmlSanitizer,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function create(UploadedFile $file, string $group, string $title, string $locale): CarouselModel
    {
        $con = Propel::getConnection(CarouselTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $slide = (new CarouselModel())
                ->setGroup($group)
                ->setPosition($this->nextPositionInGroup($group))
                ->setDisable(0)
                ->setLimited(0);

            $slide
                ->setLocale($locale)
                ->setTitle($title)
                ->save($con);

            $this->attachImage($slide, $file, ImageVariant::Desktop);

            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollBack();

            throw $exception;
        }

        return $slide;
    }

    /**
     * @param array<string, mixed> $data validated CarouselSlideForm data
     */
    public function update(CarouselModel $slide, array $data, string $locale): void
    {
        $con = Propel::getConnection(CarouselTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $previousGroup = $slide->getGroup();
            $newGroup = (string) $data['group'];

            $slide
                ->setGroup($newGroup)
                ->setUrl($data['url'])
                ->setLinkTarget($data['link_target'])
                ->setDisable($data['visible'] ? 0 : 1)
                ->setLimited($data['limited'] ? 1 : 0)
                ->setStartDate($data['start_date'])
                ->setEndDate($data['end_date'])
                ->setLocale($locale)
                ->setTitle($data['title'])
                ->setAlt($data['alt'])
                ->setChapo($data['chapo'])
                ->setDescription($this->htmlSanitizer->sanitize($data['description']))
                ->setPostscriptum($data['postscriptum'])
                ->setButtonLabel($data['button_label']);

            if ($previousGroup !== $newGroup) {
                $slide->setPosition($this->nextPositionInGroup($newGroup));
            }

            $slide->save($con);

            if ($previousGroup !== $newGroup) {
                $this->renumberGroup($previousGroup, $con);
            }

            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollBack();

            throw $exception;
        }
    }

    public function delete(int $slideId): void
    {
        $slide = CarouselQuery::create()->findPk($slideId);

        if ($slide === null) {
            return;
        }

        $group = $slide->getGroup();

        $con = Propel::getConnection(CarouselTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            $slide->delete($con);
            $this->renumberGroup($group, $con);

            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollBack();

            throw $exception;
        }
    }

    public function updatePosition(int $slideId, int $position): void
    {
        $slide = CarouselQuery::create()->findPk($slideId);

        if ($slide === null) {
            return;
        }

        $siblings = CarouselQuery::create()
            ->filterByGroup($slide->getGroup())
            ->filterById($slideId, Criteria::NOT_EQUAL)
            ->orderByPosition()
            ->find()
            ->getData();

        array_splice($siblings, max(0, $position - 1), 0, [$slide]);

        $con = Propel::getConnection(CarouselTableMap::DATABASE_NAME);
        $con->beginTransaction();

        try {
            foreach ($siblings as $index => $sibling) {
                if ($sibling->getPosition() !== $index + 1) {
                    $sibling->setPosition($index + 1)->save($con);
                }
            }

            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollBack();

            throw $exception;
        }
    }

    public function toggleVisibility(int $slideId): bool
    {
        $slide = CarouselQuery::create()->findPk($slideId);

        if ($slide === null) {
            return false;
        }

        $visible = (bool) $slide->getDisable();
        $slide->setDisable($visible ? 0 : 1)->save();

        return $visible;
    }

    public function attachImage(CarouselModel $slide, UploadedFile $uploadedFile, ImageVariant $variant): void
    {
        $uploadDir = $slide->getUploadDir();
        $filesystem = new Filesystem();

        if (!$filesystem->exists($uploadDir)) {
            $filesystem->mkdir($uploadDir);
        }

        $previousFile = match ($variant) {
            ImageVariant::Desktop => $slide->getFile(),
            ImageVariant::Mobile => $slide->getMobileFile(),
        };

        $fileName = $this->buildFileName($slide, $uploadedFile, $variant);
        $filePath = $uploadDir.DS.$fileName;

        $filesystem->rename($uploadedFile->getPathname(), $filePath, true);
        $filesystem->chmod($filePath, 0o660);

        // Remove the previous file only when the new name differs: a re-upload of
        // the same source name produces the same deterministic name, and deleting
        // "the old one" would delete the file we just wrote.
        if ($previousFile !== null && $previousFile !== '' && $previousFile !== $fileName) {
            $filesystem->remove($uploadDir.DS.$previousFile);
        }

        match ($variant) {
            ImageVariant::Desktop => $slide->setFile($fileName),
            ImageVariant::Mobile => $slide->setMobileFile($fileName),
        };

        $slide->save();

        $this->clearImageCache();
    }

    public function removeImage(CarouselModel $slide, ImageVariant $variant): void
    {
        if ($variant === ImageVariant::Desktop) {
            // The desktop image is mandatory: it can only be replaced, never removed.
            return;
        }

        $this->removeImageFile($slide, $variant);

        $slide->setMobileFile(null)->save();

        $this->clearImageCache();
    }

    /**
     * Purges the carousel image cache: Thelia only regenerates a cached variant
     * when the file is missing, so a same-name replacement would keep serving the
     * stale image (and the previous file's variants would be orphaned) otherwise.
     */
    private function clearImageCache(): void
    {
        $this->eventDispatcher->dispatch(
            (new CachedFileEvent())->setCacheSubdirectory('carousel'),
            TheliaEvents::IMAGE_CLEAR_CACHE,
        );
    }

    private function removeImageFile(CarouselModel $slide, ImageVariant $variant): void
    {
        $currentFile = match ($variant) {
            ImageVariant::Desktop => $slide->getFile(),
            ImageVariant::Mobile => $slide->getMobileFile(),
        };

        if ($currentFile === null || $currentFile === '') {
            return;
        }

        (new Filesystem())->remove($slide->getUploadDir().DS.$currentFile);
    }

    private function buildFileName(CarouselModel $slide, UploadedFile $uploadedFile, ImageVariant $variant): string
    {
        // Derive the stored extension from the server-detected MIME type, never
        // from the client-controlled original name/extension: a ".php" uploaded
        // as an image would otherwise be symlinked, executable, into the web cache.
        $extension = MimeTypes::getDefault()->getExtensions($uploadedFile->getMimeType() ?? '')[0] ?? 'bin';
        $baseName = pathinfo($uploadedFile->getClientOriginalName(), \PATHINFO_FILENAME);

        // Suffix the variant so both images of a slide never collide on disk.
        return $this->fileManager->sanitizeFileName(
            \sprintf('%s-%d-%s.%s', $baseName, $slide->getId(), $variant->value, $extension),
        );
    }

    private function nextPositionInGroup(?string $group): int
    {
        $maxPosition = CarouselQuery::create()
            ->filterByGroup($group)
            ->orderByPosition(Criteria::DESC)
            ->findOne()
            ?->getPosition();

        return ($maxPosition ?? 0) + 1;
    }

    private function renumberGroup(?string $group, ConnectionInterface $con): void
    {
        $slides = CarouselQuery::create()
            ->filterByGroup($group)
            ->orderByPosition()
            ->find();

        foreach ($slides as $index => $slide) {
            if ($slide->getPosition() !== $index + 1) {
                $slide->setPosition($index + 1)->save($con);
            }
        }
    }
}
