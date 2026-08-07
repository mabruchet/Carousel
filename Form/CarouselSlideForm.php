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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

class CarouselSlideForm extends BaseForm
{
    protected function buildForm(): void
    {
        $translator = Translator::getInstance();

        $this->formBuilder
            ->add('locale', HiddenType::class)
            ->add('title', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $translator->trans('Title', [], Carousel::DOMAIN_NAME),
            ])
            ->add('alt', TextType::class, [
                'required' => false,
                'label' => $translator->trans('Alternative image text', [], Carousel::DOMAIN_NAME),
            ])
            ->add('chapo', TextareaType::class, [
                'required' => false,
                'label' => $translator->trans('Summary', [], Carousel::DOMAIN_NAME),
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => $translator->trans('Detailed description', [], Carousel::DOMAIN_NAME),
            ])
            ->add('postscriptum', TextareaType::class, [
                'required' => false,
                'label' => $translator->trans('Conclusion', [], Carousel::DOMAIN_NAME),
            ])
            ->add('url', TextType::class, [
                'required' => false,
                'constraints' => [new Url(message: $translator->trans('Please enter a valid URL', [], Carousel::DOMAIN_NAME), requireTld: true)],
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
            ->add('group', TextType::class, [
                'constraints' => [new NotBlank()],
                'label' => $translator->trans('Group image', [], Carousel::DOMAIN_NAME),
            ])
            ->add('visible', CheckboxType::class, [
                'required' => false,
                'label' => $translator->trans('Visible', [], Carousel::DOMAIN_NAME),
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

        $this->formBuilder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use ($translator): void {
            $form = $event->getForm();

            if (!$form->get('limited')->getData()) {
                return;
            }

            $start = $form->get('start_date')->getData();
            $end = $form->get('end_date')->getData();

            if ($start === null || $end === null || $end < $start) {
                $form->get('end_date')->addError(
                    new FormError($translator->trans('The end date must be after the start date', [], Carousel::DOMAIN_NAME)),
                );
            }
        });
    }

    public static function getName(): string
    {
        return 'carousel_slide';
    }
}
