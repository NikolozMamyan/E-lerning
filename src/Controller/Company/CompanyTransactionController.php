<?php

namespace App\Controller\Company;

use App\Entity\Progress;
use App\Repository\VideoRepository;
use App\Repository\ProgressRepository;
use App\Repository\EnrollmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\QuizAttemptRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CompanyTransactionController extends AbstractController
{
    
#[Route('/company/transactions', name: 'app_co_transactions')]
public function transactions(
    EnrollmentRepository $enrollmentRepo
): Response {
    $user = $this->getUser();

    if (!$user) {
        throw $this->createAccessDeniedException('Vous devez être connecté.');
    }

    // récupère les enrollments de l’utilisateur avec leurs cours
    $enrollments = $enrollmentRepo->findByUserWithCourse($user);
    $subscription = $user->hasActiveSubscription();


    return $this->render('company/transaction/transactions.html.twig', [
        'enrollments' => $enrollments,
        'hasSubscription' => $subscription,
    ]);
}


}
