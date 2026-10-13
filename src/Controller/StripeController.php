<?php

namespace App\Controller;

use Stripe\Stripe;
use App\Service\LearnerCheckoutService;
use App\Entity\QuizAttempt;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class StripeController extends AbstractController
{
    #[Route('api/pay/course/{id}', name: 'app_course_checkout', methods: ['POST'])]
    public function checkoutCourse(int $id, CourseRepository $courseRepo, LearnerCheckoutService $checkout): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('show_login');
        }

        $course = $courseRepo->find($id);
        if (!$course) {
            throw $this->createNotFoundException();
        }

        return $this->redirect($checkout->course($user, $course), 303);
    }

      #[Route('app/pay/success', name: 'app_payment_success')]
    public function success(
        Request $request, 
        CourseRepository $courseRepo, 
        EntityManagerInterface $em
    ): Response {
        $sessionId = $request->query->get('session_id');

        $vars = [
            'transaction_id' => null,
            'amount'         => null,
            'currency'       => 'EUR',
            'course_id'      => null,
            'course_title'   => null,
        ];

        if ($sessionId) {
            \Stripe\Stripe::setApiKey($_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY']);

            $session = \Stripe\Checkout\Session::retrieve([
                'id' => $sessionId,
                'expand' => ['payment_intent', 'line_items'],
            ]);

            $paymentIntentId = is_string($session->payment_intent)
                ? $session->payment_intent
                : ($session->payment_intent->id ?? null);

            $amountCents = $session->amount_total ?? null;
            $currency    = strtoupper($session->currency ?? 'EUR');

            $courseId    = $session->metadata['course_id'] ?? null;
            $userId      = $session->metadata['user_id'] ?? null;

            $courseTitle = null;
            if ($courseId) {
                $course = $courseRepo->find($courseId);
                $courseTitle = $course?->getTitle();
            }

            // 🔑 SUPPRESSION de la dernière tentative si elle existe
            if ($userId && $courseId) {
                $attempt = $em->getRepository(QuizAttempt::class)->findOneBy([
                    'user'   => $userId,
                    'course' => $courseId
                ], ['id' => 'DESC']); // on prend la dernière

                if ($attempt) {
                    $em->remove($attempt);
                    $em->flush();
                }
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
