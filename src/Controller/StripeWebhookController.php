<?php
namespace App\Controller;

use App\Entity\Enrollment;
use Psr\Log\LoggerInterface;
use App\Service\MailerService;
use App\Repository\UserRepository;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class StripeWebhookController extends AbstractController
{
    #[Route('/api/stripe/webhook', name: 'app_stripe_webhook', methods: ['POST'])]
    public function __invoke(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepo,
        CourseRepository $courseRepo,
        EnrollmentRepository $enrollmentRepo,
        MailerService $mailer,
        LoggerInterface $logger
    ): Response {


        $payload   = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');
        $secret    = $_SERVER['STRIPE_WEBHOOK_SECRET'] ?? $_ENV['STRIPE_WEBHOOK_SECRET'] ?? null;

        if (!$secret) {
            return new Response('Webhook secret not configured', 500);
        }

        try {
            \Stripe\Stripe::setApiKey($_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY']);
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException) {
            return new Response('Invalid payload', 400);
        } catch (\Stripe\Exception\SignatureVerificationException) {
            return new Response('Invalid signature', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            /** @var \Stripe\Checkout\Session $session */
            $session = $event->data->object;

            // En mode "payment", on vérifie que c'est bien payé
            if (($session->payment_status ?? null) !== 'paid') {
                return new Response('Ignored: not paid', 200);
            }

            $userId   = $session->metadata['user_id']   ?? null;
            $courseId = $session->metadata['course_id'] ?? null;

            $user   = $userId ? $userRepo->find($userId) : null;
            if (!$user && !empty($session->customer_email)) {
                $user = $userRepo->findOneBy(['email' => $session->customer_email]);
            }
            $course = $courseId ? $courseRepo->find($courseId) : null;

            if (!$user || !$course) {
                $logger->warning('Webhook: user or course not found', [
                    'userId' => $userId, 'courseId' => $courseId, 'sessionId' => $session->id ?? null
                ]);
                return new Response('User or course not found', 200);
            }

            // Idempotence
            $existing = $enrollmentRepo->findOneBy(['user' => $user, 'course' => $course]);
            if ($existing) {
                return new Response('Enrollment already exists', 200);
            }

            $enrollment = new Enrollment();
            $enrollment->setUser($user);
            $enrollment->setCourse($course);
            // createdAt est déjà défini dans le constructeur

            // Optionnel si tu as ajouté les champs
            if (property_exists(Enrollment::class, 'stripePaymentIntentId')) {
                $paymentIntentId = is_string($session->payment_intent)
                    ? $session->payment_intent
                    : ($session->payment_intent->id ?? null);
                if (method_exists($enrollment, 'setStripePaymentIntentId')) {
                    $enrollment->setStripePaymentIntentId($paymentIntentId);
                }
            }
            if (property_exists(Enrollment::class, 'stripeSessionId') && method_exists($enrollment, 'setStripeSessionId')) {
                $enrollment->setStripeSessionId($session->id ?? null);
            }

            $em->persist($enrollment);
            $em->flush();

            // ✅ Envoi facture
        $amount = $session->amount_total / 100; // Stripe en centimes
        $tva = null; 

        $mailer->send(
            $user->getEmail(),
            'Votre facture - ' . $course->getTitle(),
            'emails/invoice.html.twig',
            [
                'user'   => $user,
                'course' => $course,
                'amount' => $amount,
                'tva'    => $tva,
                'date'   => new \DateTime(),
            ]
        );

            return new Response('Enrollment created', 200);
        }

        return new Response('Event ignored', 200);
    }
}
