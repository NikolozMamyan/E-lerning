<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Service\GuestCourseCheckoutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicCatalogController extends AbstractController
{
    #[Route('/catalog', name: 'app_public_catalog', methods: ['GET'])]
    public function index(CourseRepository $courseRepository): Response
    {
        return $this->render('catalog/index.html.twig', [
            'courses' => $courseRepository->findBy([], ['title' => 'ASC']),
        ]);
    }

    #[Route('/catalog/course/{id}/checkout', name: 'app_catalog_course_checkout', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function checkout(Course $course, Request $request, GuestCourseCheckoutService $checkoutService): Response
    {
        if (!$this->isCsrfTokenValid('catalog_checkout_'.$course->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid checkout request.');
        }

        try {
            $user = $this->getUser();
            $session = $checkoutService->createCheckout($course, $user instanceof User ? $user : null);
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirect($this->generateUrl('app_public_catalog').'#course-'.$course->getId());
        }

        return $this->redirect((string) $session->url, Response::HTTP_SEE_OTHER);
    }

    #[Route('/catalog/payment/success', name: 'app_catalog_payment_success', methods: ['GET'])]
    public function success(Request $request, GuestCourseCheckoutService $checkoutService, CourseRepository $courseRepository): Response
    {
        $sessionId = trim((string) $request->query->get('session_id'));
        $summary = ['paid' => false, 'courseId' => null, 'courseTitle' => null, 'amount' => null, 'currency' => 'EUR'];

        if ($sessionId !== '') {
            try {
                $summary = $checkoutService->paymentSummary($sessionId);
                $course = $summary['courseId'] ? $courseRepository->find($summary['courseId']) : null;
                $summary['courseTitle'] = $course?->getTitle();
            } catch (\Throwable) {
                $this->addFlash('warning', 'The payment details are still being confirmed. Please check your email shortly.');
            }
        }

        return $this->render('catalog/success.html.twig', ['payment' => $summary]);
    }
}
