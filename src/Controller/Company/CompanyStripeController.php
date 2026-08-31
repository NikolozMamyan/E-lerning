<?php

declare(strict_types=1);

namespace App\Controller\Company;

use App\Entity\User;
use App\Repository\CourseRepository;
use App\Service\CompanyCourseCheckoutService;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompanyStripeController extends AbstractController
{
    #[Route('/company/pay/course/{id}', name: 'company_course_checkout', methods: ['POST'])]
    public function checkoutCourse(
        int $id,
        Request $request,
        CourseRepository $courseRepository,
        CompanyCourseCheckoutService $checkoutService,
    ): Response {
        $company = $this->getUser();
        if (!$company instanceof User) {
            return $this->redirectToRoute('show_login');
        }

        if (!$this->isCsrfTokenValid('company_checkout', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid checkout token.');
        }

        $course = $courseRepository->find($id);
        if ($course === null) {
            throw $this->createNotFoundException('Course not found.');
        }

        try {
            $session = $checkoutService->createCheckout(
                $course,
                $company,
                $request->request->all('employee_ids'),
            );
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('company_courses');
        }

        return $this->redirect((string) $session->url, 303);
    }

    #[Route('/company/pay/success', name: 'company_payment_success', methods: ['GET'])]
    public function success(Request $request, CourseRepository $courseRepository): Response
    {
        $sessionId = $request->query->getString('session_id');
        $variables = [
            'hasSession' => false,
            'context' => null,
            'courseTitle' => null,
            'courseId' => null,
            'targetEmail' => null,
            'quantity' => null,
            'buyerEmail' => null,
            'amount' => null,
            'currency' => 'EUR',
            'paymentStatus' => null,
        ];

        if ($sessionId !== '') {
            try {
                $this->configureStripe();

                /** @var Session $session */
                $session = Session::retrieve([
                    'id' => $sessionId,
                    'expand' => ['payment_intent', 'line_items.data.price.product'],
                ]);

                $metadata = $session->metadata ?? (object) [];
                $courseId = isset($metadata->course_id) ? (int) $metadata->course_id : null;
                $course = $courseId !== null ? $courseRepository->find($courseId) : null;

                $variables = [
                    'hasSession' => true,
                    'context' => $metadata->context ?? null,
                    'courseTitle' => $course?->getTitle(),
                    'courseId' => $courseId,
                    'targetEmail' => $metadata->target_email ?? null,
                    'quantity' => isset($metadata->quantity) ? (int) $metadata->quantity : null,
                    'buyerEmail' => $session->customer_details->email ?? $session->customer_email ?? null,
                    'amount' => isset($session->amount_total) ? $session->amount_total / 100 : null,
                    'currency' => strtoupper((string) ($session->currency ?? 'EUR')),
                    'paymentStatus' => $session->payment_status ?? null,
                ];
            } catch (\Throwable) {
                $this->addFlash('warning', 'The payment summary could not be retrieved, but paid access will still be activated automatically.');
            }
        }

        return $this->render('company/payments/success.html.twig', $variables);
    }

    #[Route('/company/pay/cancel', name: 'company_payment_cancel', methods: ['GET'])]
    public function cancel(Request $request): Response
    {
        $courseId = $request->query->get('c');

        return $this->render('company/payments/cancel.html.twig', [
            'courseId' => $courseId,
            'backUrl' => $this->generateUrl('company_courses', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    private function configureStripe(): void
    {
        $secret = $_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY'] ?? null;
        if (!is_string($secret) || $secret === '') {
            throw new \RuntimeException('Stripe is not configured.');
        }

        Stripe::setApiKey($secret);
    }
}
