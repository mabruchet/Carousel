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

namespace Carousel\Loop;

use Carousel\Event\CarouselEvents;
use Carousel\Event\Loop\CarouselLoopArgumentEvent;
use Carousel\Event\Loop\CarouselLoopCriteriaEvent;
use Carousel\Event\Loop\CarouselLoopRowEvent;
use Carousel\Model\CarouselQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Image;
use Thelia\Log\Tlog;
use Thelia\Type\EnumListType;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * The carousel loop.
 *
 * Extension points (see CarouselEvents): LOOP_DEFINE_ARGS to add arguments,
 * LOOP_EXTEND_CRITERIA to alter the query (front filters, joins…), and
 * LOOP_ENRICH_ROW to add output variables — no fork needed.
 *
 * @method int[]       getId()
 * @method int         getWidth()
 * @method int         getHeight()
 * @method int         getRotation()
 * @method string      getBackgroundColor()
 * @method int         getQuality()
 * @method string      getResizeMode()
 * @method string[]    getOrder()
 * @method string      getEffects()
 * @method bool        getAllowZoom()
 * @method bool        getFilterDisableSlides()
 * @method bool        getBackendContext()
 * @method bool        getBackOfficeLocation()
 * @method string      getGroup()
 * @method string      getFormat()
 */
class Carousel extends Image
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        $arguments = new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('width'),
            Argument::createIntTypeArgument('height'),
            Argument::createIntTypeArgument('rotation', 0),
            Argument::createAnyTypeArgument('background_color'),
            Argument::createIntTypeArgument('quality'),
            new Argument(
                'resize_mode',
                new TypeCollection(
                    new EnumType(['crop', 'borders', 'none'])
                ),
                'none'
            ),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['alpha', 'alpha-reverse', 'manual', 'manual-reverse', 'random'])
                ),
                'manual'
            ),
            Argument::createAnyTypeArgument('effects'),
            Argument::createBooleanTypeArgument('allow_zoom', false),
            Argument::createBooleanTypeArgument('filter_disable_slides', true),
            Argument::createBooleanTypeArgument('backend_context', false),
            // Deprecated alias of backend_context, kept for backward compatibility.
            Argument::createBooleanTypeArgument('back_office_location', false),
            Argument::createAlphaNumStringTypeArgument('group'),
            Argument::createAlphaNumStringTypeArgument('format')
        );

        $this->dispatcher?->dispatch(
            new CarouselLoopArgumentEvent($arguments),
            CarouselEvents::LOOP_DEFINE_ARGS
        );

        return $arguments;
    }

    protected function isBackendContext(): bool
    {
        return (bool) ($this->getBackendContext() || $this->getBackOfficeLocation());
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = CarouselQuery::create();

        $this->configureI18nProcessing($search, ['ALT', 'TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'BUTTON_LABEL']);

        // Results ordering
        foreach ($this->getOrder() as $order) {
            switch ($order) {
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha-reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual-reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'random':
                    $search->clearOrderByColumns();
                    $search->addAscendingOrderByColumn('RAND()');
                    break 2;
            }
        }

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if ($group = $this->getGroup()) {
            $search->filterByGroup($group);
        }

        if ($this->getFilterDisableSlides()) {
            // Publication rule, evaluated in SQL: the loop no longer writes the
            // computed state back to the `disable` column at render time.
            $search
                ->where('(carousel.disable IS NULL OR carousel.disable = 0)')
                ->where('(carousel.limited IS NULL OR carousel.limited = 0 OR (carousel.start_date <= NOW() AND (carousel.end_date IS NULL OR carousel.end_date >= NOW())))');
        }

        $this->dispatcher?->dispatch(
            new CarouselLoopCriteriaEvent($search, $this->isBackendContext(), [
                'group' => $this->getGroup(),
                'filter_disable_slides' => $this->getFilterDisableSlides(),
            ]),
            CarouselEvents::LOOP_EXTEND_CRITERIA
        );

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var \Carousel\Model\Carousel $slide */
        foreach ($loopResult->getResultDataCollection() as $slide) {
            $imgSourcePath = $slide->getUploadDir().DS.$slide->getFile();
            if (!file_exists($imgSourcePath)) {
                Tlog::getInstance()->error(sprintf('Carousel source image file %s does not exists.', $imgSourcePath));
                continue;
            }

            $loopResultRow = new LoopResultRow($slide);

            $event = $this->processImage($imgSourcePath);

            $mobileImageUrl = null;
            $mobileFile = $slide->getMobileFile();
            if ($mobileFile !== null && $mobileFile !== '') {
                $mobileSourcePath = $slide->getUploadDir().DS.$mobileFile;
                if (file_exists($mobileSourcePath)) {
                    $mobileImageUrl = $this->processImage($mobileSourcePath)->getFileUrl();
                } else {
                    Tlog::getInstance()->error(sprintf('Carousel mobile image file %s does not exists.', $mobileSourcePath));
                }
            }

            $startDate = $slide->getStartDate();
            $endDate = $slide->getEndDate();
            if ($startDate) {
                $startDate = $startDate->format('Y-m-d').'T'.$startDate->format('H:i');
            }
            if ($endDate) {
                $endDate = $endDate->format('Y-m-d').'T'.$endDate->format('H:i');
            }

            $buttonLabel = $slide->getVirtualColumn('i18n_BUTTON_LABEL');

            $loopResultRow
                ->set('ID', $slide->getId())
                ->set('LOCALE', $this->locale)
                ->set('IMAGE_URL', $event->getFileUrl())
                ->set('ORIGINAL_IMAGE_URL', $event->getOriginalFileUrl())
                ->set('IMAGE_PATH', $event->getCacheFilepath())
                ->set('ORIGINAL_IMAGE_PATH', $event->getSourceFilepath())
                ->set('MOBILE_IMAGE_URL', $mobileImageUrl)
                ->set('TITLE', $slide->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $slide->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $slide->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $slide->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('ALT', $slide->getVirtualColumn('i18n_ALT'))
                ->set('URL', $slide->getUrl())
                ->set('LINK_TARGET', $slide->getLinkTarget())
                ->set('BUTTON_LABEL', $buttonLabel)
                // Historical output name kept for themes written against the scal fork.
                ->set('LABEL_BUTTON', $buttonLabel)
                ->set('POSITION', $slide->getPosition())
                ->set('DISABLE', $slide->getDisable())
                ->set('GROUP', $slide->getGroup())
                ->set('LIMITED', $slide->getLimited())
                ->set('START_DATE', $startDate)
                ->set('END_DATE', $endDate);

            $this->dispatcher?->dispatch(
                new CarouselLoopRowEvent($loopResultRow, $slide, $this->isBackendContext()),
                CarouselEvents::LOOP_ENRICH_ROW
            );

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    protected function processImage(string $sourcePath): ImageEvent
    {
        $event = new ImageEvent();
        $event->setSourceFilepath($sourcePath)
            ->setCacheSubdirectory('carousel');

        $resizeMode = match ($this->getResizeMode()) {
            'crop' => \Thelia\Action\Image::EXACT_RATIO_WITH_CROP,
            'borders' => \Thelia\Action\Image::EXACT_RATIO_WITH_BORDERS,
            default => \Thelia\Action\Image::KEEP_IMAGE_RATIO,
        };

        if (null !== $width = $this->getWidth()) {
            $event->setWidth($width);
        }
        if (null !== $height = $this->getHeight()) {
            $event->setHeight($height);
        }
        $event->setResizeMode($resizeMode);
        if (null !== $rotation = $this->getRotation()) {
            $event->setRotation($rotation);
        }
        if (null !== $backgroundColor = $this->getBackgroundColor()) {
            $event->setBackgroundColor($backgroundColor);
        }
        if (null !== $quality = $this->getQuality()) {
            $event->setQuality($quality);
        }
        if (null !== $effects = $this->getEffects()) {
            $event->setEffects($effects);
        }
        if (null !== $format = $this->getFormat()) {
            $event->setFormat($format);
        }
        $event->setAllowZoom($this->getAllowZoom());

        $this->dispatcher->dispatch($event, TheliaEvents::IMAGE_PROCESS);

        return $event;
    }
}
