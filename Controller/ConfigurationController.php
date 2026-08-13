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

namespace Carousel\Controller;

use Carousel\Carousel;
use Carousel\Form\CarouselCreateForm;
use Carousel\Form\CarouselImageForm;
use Carousel\Form\CarouselSlideForm;
use Carousel\Model\Carousel as CarouselModel;
use Carousel\Model\CarouselQuery;
use Carousel\Service\CarouselPresenter;
use Carousel\Service\CarouselSlideService;
use Carousel\Service\ImageVariant;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\LangQuery;
use Thelia\Tools\TokenProvider;
use Thelia\Tools\URL;

class ConfigurationController extends BaseAdminController
{
    #[Route('/admin/module/carousel/create', name: 'carousel.create', methods: ['POST'])]
    public function createSlide(CarouselSlideService $slideService): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::CREATE)) {
            return $response;
        }

        $form = $this->createForm(CarouselCreateForm::class);

        try {
            $data = $this->validateForm($form)->getData();

            $slide = $slideService->create(
                $data['file'],
                $data['mobile_file'],
                $data['group'],
                $data['title'],
                $this->getCurrentEditionLocale(),
            );

            return new RedirectResponse($this->editUrl($slide->getId()));
        } catch (FormValidationException $exception) {
            $this->setupFormErrorContext(
                'carousel slide creation',
                $this->createStandardFormValidationErrorMessage($exception),
                $form,
            );

            return $this->redirectToConfigurationPage();
        }
    }

    #[Route('/admin/module/carousel/edit/{slideId}', name: 'carousel.edit', methods: ['GET'], requirements: ['slideId' => '\d+'])]
    public function editSlide(int $slideId, CarouselPresenter $presenter): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        if (null === $slide = $this->findSlide($slideId)) {
            return $this->pageNotFound();
        }

        $locale = $this->getCurrentEditionLocale();
        $presented = $presenter->present($slide, $locale, 550, 200);

        $form = $this->createForm(CarouselSlideForm::class, FormType::class, [
            'locale' => $locale,
            'title' => $presented['title'],
            'alt' => $presented['alt'],
            'chapo' => $presented['chapo'],
            'description' => $presented['description'],
            'postscriptum' => $presented['postscriptum'],
            'url' => $presented['url'],
            'link_target' => $presented['linkTarget'],
            'button_label' => $presented['buttonLabel'],
            'group' => $presented['group'],
            'visible' => $presented['visible'],
            'limited' => $presented['limited'],
            'start_date' => $presented['startDate'],
            'end_date' => $presented['endDate'],
        ]);

        return $this->render('carousel/slide-edit', [
            'slide' => $presented,
            'slide_form' => $form->createView()->getView(),
            'image_form' => $this->createForm(CarouselImageForm::class)->createView()->getView(),
            'locale' => $locale,
            'edit_language_id' => LangQuery::create()->filterByLocale($locale)->findOne()?->getId() ?? 1,
        ]);
    }

    #[Route('/admin/module/carousel/edit/{slideId}', name: 'carousel.save', methods: ['POST'], requirements: ['slideId' => '\d+'])]
    public function saveSlide(int $slideId, CarouselSlideService $slideService): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        if (null === $slide = $this->findSlide($slideId)) {
            return $this->pageNotFound();
        }

        // The mobile image is mandatory: block saving a slide (typically legacy
        // data) that has none until one is uploaded.
        if ($slide->getMobileFile() === null || $slide->getMobileFile() === '') {
            $this->addFlash(
                'danger',
                Translator::getInstance()->trans('Please upload a mobile image before saving this slide.', [], Carousel::BO_DOMAIN),
            );

            return new RedirectResponse($this->editUrl($slideId));
        }

        $form = $this->createForm(CarouselSlideForm::class);

        try {
            $data = $this->validateForm($form)->getData();

            $slideService->update($slide, $data, $data['locale'] ?: $this->getCurrentEditionLocale());
        } catch (FormValidationException $exception) {
            $this->setupFormErrorContext(
                'carousel slide edition',
                $this->createStandardFormValidationErrorMessage($exception),
                $form,
            );
        }

        return new RedirectResponse($this->editUrl($slideId));
    }

    #[Route('/admin/module/carousel/image/{slideId}/{variant}', name: 'carousel.image.upload', methods: ['POST'], requirements: ['slideId' => '\d+', 'variant' => 'desktop|mobile'])]
    public function uploadImage(int $slideId, string $variant, CarouselSlideService $slideService): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        if (null === $slide = $this->findSlide($slideId)) {
            return $this->pageNotFound();
        }

        $form = $this->createForm(CarouselImageForm::class);

        try {
            $data = $this->validateForm($form)->getData();

            $slideService->attachImage($slide, $data['file'], ImageVariant::from($variant));
        } catch (FormValidationException $exception) {
            $this->setupFormErrorContext(
                'carousel image upload',
                $this->createStandardFormValidationErrorMessage($exception),
                $form,
            );
        }

        return new RedirectResponse($this->editUrl($slideId));
    }

    #[Route('/admin/module/carousel/update-position', name: 'carousel.update_position', methods: ['POST'])]
    public function updatePosition(Request $request, TokenProvider $tokenProvider, CarouselSlideService $slideService): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        $tokenProvider->checkToken((string) $request->get('_token'));

        $slideService->updatePosition((int) $request->get('slide_id'), (int) $request->get('position'));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/admin/module/carousel/toggle-visible/{slideId}', name: 'carousel.toggle', methods: ['GET'], requirements: ['slideId' => '\d+'])]
    public function toggleVisibility(int $slideId, Request $request, TokenProvider $tokenProvider, CarouselSlideService $slideService): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        $tokenProvider->checkToken((string) $request->get('_token'));

        $slideService->toggleVisibility($slideId);

        return $this->redirectToConfigurationPage();
    }

    #[Route('/admin/module/carousel/delete', name: 'carousel.delete', methods: ['POST'])]
    public function deleteSlide(Request $request, TokenProvider $tokenProvider, CarouselSlideService $slideService): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::DELETE)) {
            return $response;
        }

        $tokenProvider->checkToken((string) $request->get('_token'));

        $slideService->delete((int) $request->get('slide_id'));

        return $this->redirectToConfigurationPage();
    }

    private function findSlide(int $slideId): ?CarouselModel
    {
        return CarouselQuery::create()->findPk($slideId);
    }

    private function editUrl(int $slideId): string
    {
        return URL::getInstance()->absoluteUrl('/admin/module/carousel/edit/'.$slideId);
    }

    private function redirectToConfigurationPage(): RedirectResponse
    {
        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/Carousel'));
    }
}
