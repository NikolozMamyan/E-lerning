<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class SubscriptionController extends AbstractController
{
    #[Route('/app/pay/subscription/plan', name: 'app_subscription')]
    public function subscription(): Response
    {

        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('show_login');
        }

        $user = $this->getUser();
        $subscription = $user->hasActiveSubscription();
      
        return $this->render('subscription/index.html.twig', [
            'subscription' => $subscription,
        ]);
    }

    #[Route('/app/pay/subscription', name: 'app_subscription_checkout')]
    public function checkoutSubscription(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('show_login');
        }

        \Stripe\Stripe::setApiKey($_SERVER['STRIPE_SECRET_KEY'] ?? $_ENV['STRIPE_SECRET_KEY']);

        // ⚙️ ID du plan Stripe (récurrent)
        $priceId = 'price_1SFEVoGYlvTXCIGyR8dbgFLA'; // à remplacer par ton vrai ID Stripe

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'subscription',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'customer_email' => $user->getEmail(),
            'metadata' => [
                'user_id' => (string) $user->getId(),
            ],
            // ✅ Redirige l’utilisateur vers une vraie page de succès
            'success_url' => $this->generateUrl('app_subscription_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
                . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $this->generateUrl('app_payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $this->redirect($session->url, 303);
    }

    #[Route('/app/pay/subscription/success', name: 'app_subscription_success')]
    public function subscriptionSuccess(): Response
    {
        return $this->render('subscription/success.html.twig', [
            'message' => '✅ Votre abonnement a bien été activé. Vous avez désormais accès à tous les cours !',
        ]);
    }
}
