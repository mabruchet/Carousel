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

declare(strict_types=1);

namespace Carousel\Command;

use Carousel\Model\Carousel as CarouselModel;
use Carousel\Model\CarouselQuery;
use Carousel\Service\CarouselSlideService;
use Carousel\Service\ImageVariant;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Command\ContainerAwareCommand;
use Thelia\Model\LangQuery;

/**
 * Seeds a carousel group with demo slides covering every publication state, so
 * the refactored back-office can be exercised end to end without hand-crafting
 * images and dates.
 */
class CarouselSeedCommand extends ContainerAwareCommand
{
    private const DESKTOP_WIDTH = 1436;
    private const DESKTOP_HEIGHT = 412;
    private const MOBILE_WIDTH = 768;
    private const MOBILE_HEIGHT = 1024;
    private const MINIMUM_COUNT = 4;

    /** Fixed palette so a re-seed always produces the same visual set. */
    private const PALETTE = [
        [0x1F, 0x77, 0xB4],
        [0xD6, 0x27, 0x28],
        [0x2C, 0xA0, 0x2C],
        [0xFF, 0x7F, 0x0E],
        [0x94, 0x67, 0xBD],
        [0x8C, 0x56, 0x4B],
        [0x17, 0xBE, 0xCF],
        [0x7F, 0x7F, 0x7F],
    ];

    public function __construct(private readonly CarouselSlideService $carouselSlideService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('carousel:seed')
            ->setDescription('Seed a carousel group with demo slides (desktop + mobile images, varied publication states)')
            ->addOption('group', null, InputOption::VALUE_REQUIRED, 'Carousel group to seed', 'demo')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Number of slides to create (minimum '.self::MINIMUM_COUNT.')', (string) self::MINIMUM_COUNT)
            ->addOption('purge', null, InputOption::VALUE_NONE, 'Delete every existing slide of the group before seeding')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\function_exists('imagecreatetruecolor') || !\function_exists('imagejpeg') || !\function_exists('imagestring')) {
            $io->error('The GD extension is required to generate the demo images (imagecreatetruecolor/imagejpeg/imagestring are missing).');

            return 1;
        }

        $group = trim((string) $input->getOption('group'));

        if ($group === '') {
            $io->error('The --group option cannot be empty.');

            return 1;
        }

        $count = (int) $input->getOption('count');

        if ($count < self::MINIMUM_COUNT) {
            $io->error(sprintf('The --count option must be at least %d (got %d).', self::MINIMUM_COUNT, $count));

            return 1;
        }

        $existing = CarouselQuery::create()->filterByGroup($group)->find();

        if ($input->getOption('purge')) {
            foreach ($existing as $slide) {
                $this->carouselSlideService->delete((int) $slide->getId());
            }

            $io->text(sprintf('Purged %d existing slide(s) of group "%s".', \count($existing), $group));
        } elseif (\count($existing) > 0) {
            $io->warning(sprintf(
                'Group "%s" already contains %d slide(s): nothing was created. Use --purge to reseed.',
                $group,
                \count($existing)
            ));

            return 0;
        }

        $locales = $this->collectLocales();
        $workingDirectory = $this->createWorkingDirectory();
        $filesystem = new Filesystem();
        $rows = [];

        try {
            for ($index = 1; $index <= $count; ++$index) {
                $slide = $this->createSlide($group, $index, $workingDirectory, $locales);
                $rows[] = [
                    $slide->getId(),
                    $slide->getPosition(),
                    $slide->getFile(),
                    $slide->getMobileFile(),
                    $this->describeState($slide),
                ];
            }
        } finally {
            $filesystem->remove($workingDirectory);
        }

        $this->carouselSlideService->clearImageCache();

        $io->success(sprintf('%d slide(s) created in group "%s".', $count, $group));
        $io->table(['id', 'position', 'desktop file', 'mobile file', 'state'], $rows);

        return 0;
    }

    /**
     * @param array<int, string> $locales
     */
    private function createSlide(string $group, int $index, string $workingDirectory, array $locales): CarouselModel
    {
        $baseName = sprintf('seed-%s-%d', $group, $index);
        [$red, $green, $blue] = self::PALETTE[($index - 1) % \count(self::PALETTE)];

        $desktopPath = $this->generateImage(
            $workingDirectory.\DIRECTORY_SEPARATOR.$baseName.'-desktop.jpg',
            self::DESKTOP_WIDTH,
            self::DESKTOP_HEIGHT,
            [$red, $green, $blue],
            sprintf('SEED %s #%d desktop', $group, $index)
        );

        $mobilePath = $this->generateImage(
            $workingDirectory.\DIRECTORY_SEPARATOR.$baseName.'-mobile.jpg',
            self::MOBILE_WIDTH,
            self::MOBILE_HEIGHT,
            [$red, $green, $blue],
            sprintf('SEED %s #%d mobile', $group, $index)
        );

        $slide = $this->carouselSlideService->create(
            $this->toUploadedFile($desktopPath, $baseName.'.jpg'),
            $group,
            $locales[0]
        );

        $this->carouselSlideService->attachImage(
            $slide,
            $this->toUploadedFile($mobilePath, $baseName.'.jpg'),
            ImageVariant::Mobile
        );

        $this->fillTranslations($slide, $index, $locales);
        $this->applyPublicationState($slide, $index);

        $slide
            ->setUrl(sprintf('https://example.com/slide-%d', $index))
            ->setLinkTarget('_self')
            ->save();

        return $slide;
    }

    /**
     * @param array<int, string> $locales
     */
    private function fillTranslations(CarouselModel $slide, int $index, array $locales): void
    {
        $french = [
            'alt' => sprintf('Visuel de démo #%d', $index),
            'title' => sprintf('Slide de démo #%d', $index),
            'chapo' => sprintf('Chapô de la slide de démonstration numéro %d.', $index),
            'description' => sprintf('<p>Description riche de la slide de démonstration numéro %d.</p>', $index),
            'postscriptum' => sprintf('Post-scriptum de la slide %d.', $index),
            'button_label' => 'En savoir plus',
        ];

        $english = [
            'alt' => sprintf('Demo visual #%d', $index),
            'title' => sprintf('Demo slide #%d', $index),
            'chapo' => sprintf('Summary of demo slide number %d.', $index),
            'description' => sprintf('<p>Rich description of demo slide number %d.</p>', $index),
            'postscriptum' => sprintf('Postscriptum of slide %d.', $index),
            'button_label' => 'Learn more',
        ];

        foreach ($locales as $locale) {
            $values = $locale === 'fr_FR' ? $french : $english;

            $slide
                ->setLocale($locale)
                ->setAlt($values['alt'])
                ->setTitle($values['title'])
                ->setChapo($values['chapo'])
                ->setDescription($values['description'])
                ->setPostscriptum($values['postscriptum'])
                ->setButtonLabel($values['button_label']);
        }

        $slide->setLocale($locales[0]);
        $slide->save();
    }

    private function applyPublicationState(CarouselModel $slide, int $index): void
    {
        switch ($index) {
            case 2:
                $slide->setDisable(1);
                break;
            case 3:
                $slide
                    ->setLimited(1)
                    ->setStartDate(new \DateTime('+7 days'))
                    ->setEndDate(new \DateTime('+14 days'));
                break;
            case 4:
                $slide
                    ->setLimited(1)
                    ->setStartDate(new \DateTime('-14 days'))
                    ->setEndDate(new \DateTime('-7 days'));
                break;
            default:
                $slide->setDisable(0)->setLimited(0);
                break;
        }

        $slide->save();
    }

    private function describeState(CarouselModel $slide): string
    {
        if ((int) $slide->getDisable() === 1) {
            return 'disabled';
        }

        if ((int) $slide->getLimited() !== 1) {
            return 'published';
        }

        $now = new \DateTime();
        $start = $slide->getStartDate();
        $end = $slide->getEndDate();

        if ($start !== null && $start > $now) {
            return 'scheduled ('.$start->format('Y-m-d').' → '.($end?->format('Y-m-d') ?? '∞').')';
        }

        if ($end !== null && $end < $now) {
            return 'expired ('.($start?->format('Y-m-d') ?? '∞').' → '.$end->format('Y-m-d').')';
        }

        return 'published (limited)';
    }

    /**
     * Locales of the site, French first so the created slide carries a usable
     * default translation.
     *
     * @return array<int, string>
     */
    private function collectLocales(): array
    {
        $locales = [];

        foreach (LangQuery::create()->find() as $lang) {
            $locales[] = (string) $lang->getLocale();
        }

        if ($locales === []) {
            $locales = ['fr_FR', 'en_US'];
        }

        usort($locales, static fn (string $a, string $b): int => ($a === 'fr_FR' ? 0 : 1) <=> ($b === 'fr_FR' ? 0 : 1));

        return $locales;
    }

    private function createWorkingDirectory(): string
    {
        $directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'carousel-seed-'.bin2hex(random_bytes(6));

        (new Filesystem())->mkdir($directory);

        return $directory;
    }

    /**
     * The service moves the file with a rename(), so the UploadedFile must be
     * built in test mode: the path is not a real PHP upload.
     */
    private function toUploadedFile(string $path, string $clientName): UploadedFile
    {
        return new UploadedFile($path, $clientName, 'image/jpeg', null, true);
    }

    /**
     * @param array{0: int, 1: int, 2: int} $color
     */
    private function generateImage(string $path, int $width, int $height, array $color, string $label): string
    {
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new \RuntimeException(sprintf('Unable to allocate a %dx%d image.', $width, $height));
        }

        try {
            $background = imagecolorallocate($image, $color[0], $color[1], $color[2]);
            $foreground = imagecolorallocate($image, 0xFF, 0xFF, 0xFF);

            if ($background === false || $foreground === false) {
                throw new \RuntimeException('Unable to allocate the image colors.');
            }

            imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $background);

            // imagestring only offers built-in fonts; font 5 is 9x15 pixels.
            $textWidth = imagefontwidth(5) * \strlen($label);
            imagestring($image, 5, (int) max(0, ($width - $textWidth) / 2), (int) max(0, ($height - imagefontheight(5)) / 2), $label, $foreground);

            if (!imagejpeg($image, $path, 85)) {
                throw new \RuntimeException(sprintf('Unable to write the demo image "%s".', $path));
            }
        } finally {
            imagedestroy($image);
        }

        return $path;
    }
}
