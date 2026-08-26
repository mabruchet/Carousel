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

namespace Carousel\Controller;

use Carousel\Event\CarouselEvents;
use Carousel\Event\CarouselSlideEvent;
use Carousel\Form\CarouselCreateForm;
use Carousel\Form\CarouselImageForm;
use Carousel\Form\CarouselSlideForm;
use Carousel\Model\CarouselQuery;
use Carousel\Service\CarouselSlideService;
use Carousel\Service\ImageVariant;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\URL;

/**
 * Per-slide CRUD of the carousel back-office. Every mutation dispatches the
 * CarouselEvents so extender modules can persist their own fields — see
 * Event/CarouselEvents.php for the extension contract.
 */
class ConfigurationController extends BaseAdminController
{
    public function createSlide(
        TheliaFormFactory $formFactory,
        EventDispatcherInterface $eventDispatcher,
        CarouselSlideService $slideService,
    ): Response {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::CREATE)) {
            return $response;
        }

        $form = $formFactory->createForm(CarouselCreateForm::class);

        try {
            $data = $this->validateForm($form)->getData();

            /** @var UploadedFile $file */
            $file = $data['file'];

            $slide = $slideService->create($file, $data['group'], $this->getCurrentEditionLocale());

            $eventDispatcher->dispatch(
                new CarouselSlideEvent($slide, new ParameterBag($data), $this->getCurrentEditionLocale()),
                CarouselEvents::SLIDE_CREATED
            );

            return new RedirectResponse($this->editUrl($slide->getId()));
        } catch (FormValidationException $exception) {
            $this->setupFormErrorContext(
                'carousel slide creation',
                $this->createStandardFormValidationErrorMessage($exception),
                $form
            );

            return $this->render('module-configure', ['module_code' => 'Carousel']);
        }
    }

    public function editSlide(Request $request): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        $slideId = (int) $request->get('slideId');

        if (null === CarouselQuery::create()->findPk($slideId)) {
            return $this->redirectToConfigurationPage();
        }

        return $this->render('carousel/slide-edit', [
            'slide_id' => $slideId,
        ]);
    }

    public function saveSlide(
        Request $request,
        TheliaFormFactory $formFactory,
        EventDispatcherInterface $eventDispatcher,
    ): Response {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        $slideId = (int) $request->get('slideId');

        if (null === $slide = CarouselQuery::create()->findPk($slideId)) {
            return $this->redirectToConfigurationPage();
        }

        $form = $formFactory->createForm(CarouselSlideForm::class);

        try {
            $data = $this->validateForm($form)->getData();
            $locale = $this->getCurrentEditionLocale();

            $event = new CarouselSlideEvent($slide, new ParameterBag($data), $locale);

            // Native fields are persisted by CarouselSlideListener (SLIDE_UPDATE),
            // extender modules persist their own fields on SLIDE_UPDATED.
            $eventDispatcher->dispatch($event, CarouselEvents::SLIDE_UPDATE);
            $eventDispatcher->dispatch($event, CarouselEvents::SLIDE_UPDATED);

            return new RedirectResponse($this->editUrl($slideId));
        } catch (FormValidationException $exception) {
            $this->setupFormErrorContext(
                'carousel slide edition',
                $this->createStandardFormValidationErrorMessage($exception),
                $form
            );

            return $this->render('carousel/slide-edit', ['slide_id' => $slideId]);
        }
    }

    public function uploadImage(
        Request $request,
        TheliaFormFactory $formFactory,
        CarouselSlideService $slideService,
    ): Response {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        $slideId = (int) $request->get('slideId');
        $variant = ImageVariant::from((string) $request->get('variant'));

        if (null === $slide = CarouselQuery::create()->findPk($slideId)) {
            return $this->redirectToConfigurationPage();
        }

        $form = $formFactory->createForm(CarouselImageForm::class);

        try {
            $data = $this->validateForm($form)->getData();

            $slideService->attachImage($slide, $data['file'], $variant);
        } catch (FormValidationException $exception) {
            $this->setupFormErrorContext(
                'carousel image upload',
                $this->createStandardFormValidationErrorMessage($exception),
                $form
            );
        }

        return new RedirectResponse($this->editUrl($slideId));
    }

    public function updatePosition(Request $request, CarouselSlideService $slideService): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        $slideService->updatePosition((int) $request->get('slide_id'), (int) $request->get('position'));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function toggleVisibility(Request $request, CarouselSlideService $slideService): Response
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::UPDATE)) {
            return $response;
        }

        $this->getTokenProvider()->checkToken((string) $request->query->get('_token'));

        $slideService->toggleVisibility((int) $request->get('slideId'));

        return $this->redirectToConfigurationPage();
    }

    public function deleteSlide(
        Request $request,
        EventDispatcherInterface $eventDispatcher,
        CarouselSlideService $slideService,
    ): Response {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, ['carousel'], AccessManager::DELETE)) {
            return $response;
        }

        $this->getTokenProvider()->checkToken((string) $request->request->get('_token', (string) $request->query->get('_token')));

        $slideId = (int) $request->get('slide_id');

        if (null !== $slide = CarouselQuery::create()->findPk($slideId)) {
            $event = new CarouselSlideEvent($slide);

            $eventDispatcher->dispatch($event, CarouselEvents::SLIDE_DELETE);
            $slideService->delete($slideId);
            $eventDispatcher->dispatch($event, CarouselEvents::SLIDE_DELETED);
        }

        return $this->redirectToConfigurationPage();
    }

    protected function editUrl(int $slideId): string
    {
        return URL::getInstance()->absoluteUrl('/admin/module/carousel/edit/'.$slideId);
    }

    protected function redirectToConfigurationPage(): RedirectResponse
    {
        return new RedirectResponse(URL::getInstance()->absoluteUrl('/admin/module/Carousel'));
    }
}
