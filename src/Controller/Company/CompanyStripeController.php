<?php
// src/Controller/Company/CompanyStripeController.php

namespace App\Controller\Company;

use App\Repository\CourseRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CompanyStripeController extends AbstractController
{
    #[Route('/company/pay/course/{id}', name: 'company_course_checkout', methods: ['POST'])]
    public function checkoutCourse(
        int $id,
        Request $request,
        CourseRepository $courseRepo
    ): Response {
        $companyUser = $this->getUser(); // admin entreprise
        if (!$companyUser) {
            return $this->redirectToRoute('show_login');
        }

        $course = $courseRepo->find($id);
        if (!$course) {
            throw $this->createNotFoundException();
        }

        // Email de l’employé demandé dans le formulaire
        $targetEmail = $request->request->get('email');
        if (!$targetEmail) {
            $this->addFlash('error', 'Veuillez saisir un email employé.');
            return $this->redirectToRoute('company_courses');
        }

        // Récupère le prix EUR (en centimes)
        $euroPrice = null;
        foreach ($course->getCoursePrices() as $price) {
            if ($price->getCurrency() === 'EUR') {
                $euroPrice = $price->getPrice();
                break;
            }
        }
        if ($euroPrice === null) {
            $this->addFlash('error', 'Aucun prix EUR trouvé pour ce cours.');
            return $this->redirectToRoute('company_courses');
        }

        \Stripe\Stripe::setApiKey($_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY']);

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $euroPrice,
                    'product_data' => ['name' => $course->getTitle()],
                ],
                'quantity' => 1,
            ]],
            // C’est l’entreprise qui paie, on peut mettre son email
            'customer_email' => $companyUser->getEmail(),
            'metadata' => [
                'company_user_id' => (string) $companyUser->getId(),
                'course_id'       => (string) $course->getId(),
                'target_email'    => $targetEmail, // <- employé à qui assigner APRÈS paiement
                'context'         => 'company_single_seat',
            ],
            'success_url' => $this->generateUrl('company_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
                . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $this->generateUrl('company_payment_cancel', ['c' => $course->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $this->redirect($session->url, 303);
    }

       #[Route('/company/pay/success', name: 'company_payment_success', methods: ['GET'])]
    public function success(Request $request, CourseRepository $courseRepo): Response
    {
        $sessionId = $request->query->get('session_id');
        $vars = [
            'hasSession'   => false,
            'context'      => null,
            'courseTitle'  => null,
            'courseId'     => null,
            'targetEmail'  => null,   // email de l’employé si achat company
            'buyerEmail'   => null,   // email de l’acheteur (company admin)
            'amount'       => null,
            'currency'     => 'EUR',
            'paymentStatus'=> null,
        ];

        if ($sessionId) {
            try {
                \Stripe\Stripe::setApiKey($_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY']);

                /** @var \Stripe\Checkout\Session $session */
                $session = \Stripe\Checkout\Session::retrieve([
                    'id' => $sessionId,
                    'expand' => ['payment_intent', 'line_items.data.price.product'],
                ]);

                $metadata = $session->metadata ?? (object)[];
                $context  = $metadata->context ?? null;

                $courseId = $metadata->course_id ?? null;
                $courseTitle = null;
                if ($courseId) {
                    $course = $courseRepo->find($courseId);
                    $courseTitle = $course?->getTitle();
                }

                $vars = [
                    'hasSession'   => true,
                    'context'      => $context, // 'company_single_seat' | 'employee_purchase' | etc.
                    'courseTitle'  => $courseTitle,
                    'courseId'     => $courseId,
                    'targetEmail'  => $metadata->target_email ?? null,
                    'buyerEmail'   => $session->customer_details->email ?? $session->customer_email ?? null,
                    'amount'       => isset($session->amount_total) ? $session->amount_total / 100 : null,
                    'currency'     => strtoupper($session->currency ?? 'EUR'),
                    'paymentStatus'=> $session->payment_status ?? null, // 'paid' attendu en mode payment
                ];
            } catch (\Throwable $e) {
                // On ne bloque pas l’affichage : on montre juste un message générique
                $this->addFlash('warning', 'Le récapitulatif du paiement n’a pas pu être récupéré, mais si le paiement est réussi, l’accès sera activé sous peu.');
            }
        }

        return $this->render('company/payments/success.html.twig', $vars);
    }

    #[Route('/company/pay/cancel', name: 'company_payment_cancel', methods: ['GET'])]
    public function cancel(Request $request): Response
    {
        $courseId = $request->query->get('c');

        return $this->render('company/payments/cancel.html.twig', [
            'courseId' => $courseId,
            'backUrl'  => $this->generateUrl('company_courses', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }
}
