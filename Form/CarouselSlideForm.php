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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Per-slide edition form. Its name is STABLE ('carousel_slide'): other modules
 * add their own fields by listening to
 * TheliaEvents::FORM_AFTER_BUILD . '.carousel_slide' and persist them through
 * the CarouselEvents::SLIDE_UPDATED / SLIDE_CREATED events — no fork needed.
 */
class CarouselSlideForm extends BaseForm
{
    /** Allowed group name pattern, shared with the create form. */
    public const GROUP_PATTERN = '[\w-]{1,64}';

    protected function buildForm(): void
    {
        $translator = Translator::getInstance();

        $this->formBuilder
            ->add('slide_id', HiddenType::class, [
                'constraints' => [new NotBlank()],
            ])
            ->add('title', TextType::class, [
                'required' => false,
                'label' => $translator->trans('Title', [], Carousel::DOMAIN_NAME),
            ])
            ->add('alt', TextType::class, [
                'required' => false,
                'label' => $translator->trans('Alternative image text', [], Carousel::DOMAIN_NAME),
            ])
            ->add('url', UrlType::class, [
                'required' => false,
                'label' => $translator->trans('Image URL', [], Carousel::DOMAIN_NAME),
            ])
            ->add('link_target', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    $translator->trans('Same window', [], Carousel::DOMAIN_NAME) => '_self',
                    $translator->trans('New window', [], Carousel::DOMAIN_NAME) => '_blank',
                ],
                'label' => $translator->trans('Link target', [], Carousel::DOMAIN_NAME),
            ])
            ->add('button_label', TextType::class, [
                'required' => false,
                'label' => $translator->trans('Button label', [], Carousel::DOMAIN_NAME),
            ])
            ->add('chapo', TextareaType::class, [
                'required' => false,
                'label' => $translator->trans('Summary', [], Carousel::DOMAIN_NAME),
                'attr' => ['rows' => 3],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => $translator->trans('Detailed description', [], Carousel::DOMAIN_NAME),
                'attr' => ['rows' => 5],
            ])
            ->add('postscriptum', TextareaType::class, [
                'required' => false,
                'label' => $translator->trans('Conclusion', [], Carousel::DOMAIN_NAME),
                'attr' => ['rows' => 3],
            ])
            ->add('group', TextType::class, [
                'constraints' => [
                    new NotBlank(),
                    new Regex([
                        'pattern' => '/^'.self::GROUP_PATTERN.'$/',
                        'message' => $translator->trans('Use only letters, digits, hyphens and underscores (max 64).', [], Carousel::DOMAIN_NAME),
                    ]),
                ],
                'label' => $translator->trans('Group image', [], Carousel::DOMAIN_NAME),
            ])
            ->add('disable', CheckboxType::class, [
                'required' => false,
                'label' => $translator->trans('Disable image', [], Carousel::DOMAIN_NAME),
            ])
            ->add('limited', CheckboxType::class, [
                'required' => false,
                'label' => $translator->trans('Limited', [], Carousel::DOMAIN_NAME),
            ])
            ->add('start_date', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'label' => $translator->trans('Start date', [], Carousel::DOMAIN_NAME),
            ])
            ->add('end_date', DateTimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'label' => $translator->trans('End date', [], Carousel::DOMAIN_NAME),
            ]);
    }

    public static function getName(): string
    {
        return 'carousel_slide';
    }
}
