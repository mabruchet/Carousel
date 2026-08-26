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

namespace Carousel\Form;

use Carousel\Carousel;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Slide creation form (stable name 'carousel_slide_creation', extensible
 * through TheliaEvents::FORM_AFTER_BUILD like the edition form).
 */
class CarouselCreateForm extends BaseForm
{
    protected function buildForm(): void
    {
        $translator = Translator::getInstance();

        $this->formBuilder
            ->add('file', FileType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Image(['mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp']]),
                ],
                'label' => $translator->trans('Carousel image', [], Carousel::DOMAIN_NAME),
            ])
            ->add('group', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Regex([
                        'pattern' => '/^'.CarouselSlideForm::GROUP_PATTERN.'$/',
                        'message' => $translator->trans('Use only letters, digits, hyphens and underscores (max 64).', [], Carousel::DOMAIN_NAME),
                    ]),
                ],
                'data' => 'home',
                'label' => $translator->trans('Group image', [], Carousel::DOMAIN_NAME),
            ]);
    }

    public static function getName(): string
    {
        return 'carousel_slide_creation';
    }
}
