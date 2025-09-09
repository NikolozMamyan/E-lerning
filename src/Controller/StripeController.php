<?php

namespace App\Controller;

use App\Repository\CourseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripeController extends AbstractController
{
    #[Route('api/pay/course/{id}', name: 'app_course_checkout', methods: ['POST'])]
    public function checkoutCourse(int $id, CourseRepository $courseRepo): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('show_login');
        }

        $course = $courseRepo->find($id);
        if (!$course) {
            throw $this->createNotFoundException();
        }

        \Stripe\Stripe::setApiKey($_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY']);

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    // Ex: 30,00 € => 3000
                    'unit_amount' => 3000,
                    'product_data' => ['name' => $course->getTitle()],
                ],
                'quantity' => 1,
            ]],
            'customer_email' => $user->getEmail(),
            'metadata' => [
                'user_id' => (string) $user->getId(),
                'course_id' => (string) $course->getId(),
            ],
            'success_url' => $this->generateUrl('app_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
                . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $this->generateUrl('app_payment_cancel', ['c' => $course->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $this->redirect($session->url, 303);
    }

    #[Route('app/pay/success', name: 'app_payment_success')]
    public function success(Request $request, CourseRepository $courseRepo): Response
    {
        $sessionId = $request->query->get('session_id');

        // valeurs par défaut si pas de session_id (rafraîchissement, accès direct, etc.)
        $vars = [
            'transaction_id' => null,
            'amount'         => null,
            'currency'       => 'EUR',
            'course_id'      => null,
            'course_title'   => null,
        ];

        if ($sessionId) {
            \Stripe\Stripe::setApiKey($_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY']);

            // Récupérer la session + le PaymentIntent
            $session = \Stripe\Checkout\Session::retrieve([
                'id' => $sessionId,
                'expand' => ['payment_intent', 'line_items'],
            ]);

            $paymentIntentId = is_string($session->payment_intent)
                ? $session->payment_intent
                : ($session->payment_intent->id ?? null);

            // amount_total est en centimes
            $amountCents = $session->amount_total ?? null;
            $currency    = strtoupper($session->currency ?? 'EUR');

            $courseId    = $session->metadata['course_id'] ?? null;
            $courseTitle = null;
            if ($courseId) {
                $course = $courseRepo->find($courseId);
                $courseTitle = $course?->getTitle();
            }

            $vars = [
                'transaction_id' => $paymentIntentId,
                'amount'         => $amountCents !== null ? $amountCents / 100 : null,
                'currency'       => $currency,
                'course_id'      => $courseId,
                'course_title'   => $courseTitle,
            ];
        }

        return $this->render('payments/success.html.twig', $vars);
    }

    #[Route('app/pay/cancel', name: 'app_payment_cancel')]
    public function cancel(Request $request): Response
    {
        $courseId = $request->query->get('c');
        return $this->render('payments/cancel.html.twig', ['courseId' => $courseId]);
    }
}
