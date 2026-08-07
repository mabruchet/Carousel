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

namespace Carousel\Form;

use Carousel\Carousel;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class CarouselCreateForm extends BaseForm
{
    protected function buildForm(): void
    {
        $translator = Translator::getInstance();

        $this->formBuilder
            ->add('file', FileType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Image(),
                ],
                'label' => $translator->trans('Desktop image', [], Carousel::DOMAIN_NAME),
            ])
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $translator->trans('Title', [], Carousel::DOMAIN_NAME),
            ])
            ->add('group', TextType::class, [
                'constraints' => [new NotBlank()],
                'data' => 'home',
                'label' => $translator->trans('Group image', [], Carousel::DOMAIN_NAME),
            ]);
    }

    public static function getName(): string
    {
        return 'carousel_create';
    }
}
