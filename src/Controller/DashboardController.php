<?php

namespace App\Controller;


use App\Service\MailerService;

use App\Repository\CourseRepository;
use App\Repository\ProgressRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\QuizAttemptRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class DashboardController extends AbstractController{


    #[Route('app/dashboard', name: 'app_dashboard')]
    public function index(
        CourseRepository $courseRepo,
        ProgressRepository $progressRepo,
        QuizAttemptRepository $quizAttemptRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        // Tous les cours
        $courses = $courseRepo->findAll();

        // Progression par cours
        $progressData = [];
        if ($user) {
            foreach ($courses as $course) {
                $videos = $course->getVideos();
                $completed = 0;
                foreach ($videos as $video) {
                    $p = $progressRepo->findOneBy(['user' => $user, 'video' => $video]);
                    if ($p && $p->isCompleted()) {
                        $completed++;
                    }
                }
                $percent = count($videos) > 0 ? round(($completed / count($videos)) * 100) : 0;
                $progressData[$course->getId()] = $percent;
            }
        }

        // Récupérer les vidéos récentes (par date de progression)
        $recentVideos = [];
        if ($user) {
            $qb = $em->createQueryBuilder()
                ->select('p', 'v', 'c')
                ->from('App\Entity\Progress', 'p')
                ->join('p.video', 'v')
                ->join('v.course', 'c')
                ->where('p.user = :user')
                ->setParameter('user', $user)
                ->orderBy('p.id', 'DESC')
                ->setMaxResults(3);

            $recentVideos = $qb->getQuery()->getResult();
        }


    // on va chercher *tous* les quiz attempts de cet utilisateur
     $userAttempts = $quizAttemptRepo->findByUser($user);

        return $this->render('dashboard/index.html.twig', [
            'courses' => $courses,
            'progressData' => $progressData,
            'recentVideos' => $recentVideos,
            'userAttempts' => $userAttempts
        ]);
    }





// #[Route('/app/test-mail', name: 'test_mail')]
// public function testMail(\Symfony\Component\Mailer\MailerInterface $mailer): Response
// {
//     $email = (new \Symfony\Component\Mime\Email())
//         ->from('nika.mamian@gmail.com') // adresse expéditeur validée dans MailerSend
//         ->to('ton-email-personnel@gmail.com') // destinataire
//         ->subject('Test MailerSend SMTP')
//         ->text('Ceci est un email de test via MailerSend SMTP.');

//     $mailer->send($email);

//     return new Response('✅ Tentative d’envoi faite.');
// }

}




