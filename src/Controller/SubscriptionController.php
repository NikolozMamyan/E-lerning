<?php

namespace App\Controller;

use App\Service\MailerService;
use App\Service\LearnerCheckoutService;
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
    public function checkoutSubscription(LearnerCheckoutService $checkout): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('show_login');
        }

        return $this->redirect($checkout->subscription($user), 303);
    }

    #[Route('/app/pay/subscription/success', name: 'app_subscription_success')]
    public function subscriptionSuccess(): Response
    {
        return $this->render('subscription/success.html.twig', [
            'message' => '✅ Votre abonnement a bien été activé. Vous avez désormais accès à tous les cours !',
        ]);
    }
}
