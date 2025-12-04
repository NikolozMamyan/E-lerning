<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Video;
use App\Entity\Course;
use App\Form\CourseType;
use App\Entity\Enrollment;
use App\Entity\QuizAnswer;
use App\Entity\QuizQuestion;
use App\Entity\Subscription;
use App\Repository\UserRepository;
use App\Repository\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\SubscriptionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    #[Route('/courses', name: 'course_index', methods: ['GET'])]
    public function index(CourseRepository $repo): Response
    {
        $courses = $repo->findBy([], ['id' => 'DESC']);

        return $this->render('admin/index.html.twig', [
            'courses' => $courses,
        ]);
    }

#[Route('/courses/new', name: 'course_new', methods: ['GET','POST'])]
public function new(Request $request, EntityManagerInterface $em): Response
{
    $course = new Course();

    // Préremplir avec une question et une réponse vide
    $question = new QuizQuestion();
    $question->addAnswer(new QuizAnswer());
    $course->addQuizQuestion($question);

    $form = $this->createForm(CourseType::class, $course);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->persist($course);
        $em->flush();

        return $this->redirectToRoute('admin_course_index');
    }

    return $this->render('admin/new.html.twig', [
        'form' => $form,
        'course' => $course,
    ]);
}



    #[Route('/courses/{id}', name: 'course_show', methods: ['GET'])]
    public function show(Course $course): Response
    {
        return $this->render('admin/show.html.twig', [
            'course' => $course,
        ]);
    }

    #[Route('/courses/{id}/edit', name: 'course_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Course $course, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->syncChildren($course);

            $em->flush();

            $this->addFlash('success', 'Cours mis à jour.');
            return $this->redirectToRoute('admin_course_show', ['id' => $course->getId()]);
        }

        return $this->render('admin/edit.html.twig', [
            'course' => $course,
            'form'   => $form,
        ]);
    }

    #[Route('/courses/{id}', name: 'course_delete', methods: ['POST'])]
    public function delete(Request $request, Course $course, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_course_'.$course->getId(), (string) $request->request->get('_token'))) {
            $em->remove($course);
            $em->flush();
            $this->addFlash('success', 'Cours supprimé.');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_course_index');
    }

    /**
     * Assure les associations côté propriétaire si les FormType n'appellent pas addXxx().
     * (utile pour Videos -> setCourse, QuizQuestions -> setCourse, QuizAnswers -> setQuestion)
     */
    private function syncChildren(Course $course): void
    {
        // Videos
        if (method_exists($course, 'getVideos')) {
            foreach ($course->getVideos() as $video) {
                if ($video instanceof Video) {
                    $video->setCourse($course);
                }
            }
        }

        // Quiz Questions + Answers
        if (method_exists($course, 'getQuizQuestions')) {
            foreach ($course->getQuizQuestions() as $question) {
                if ($question instanceof QuizQuestion) {
                    $question->setCourse($course);

                    if (method_exists($question, 'getAnswers')) {
                        foreach ($question->getAnswers() as $answer) {
                            if ($answer instanceof QuizAnswer) {
                                $answer->setQuestion($question);
                            }
                        }
                    }
                }
            }
        }
    }



    #[Route('/enrollments', name: 'enrollments', methods: ['GET'])]
public function listEnrollments(EntityManagerInterface $em): Response
{
    $admin = $this->getUser();
    if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
        return $this->redirectToRoute('show_login');
    }

    $enrollments = $em->getRepository(Enrollment::class)->findBy([], ['id' => 'DESC']);

    return $this->render('admin/enrollments.html.twig', [
        'enrollments' => $enrollments,
    ]);
}

#[Route('/enrollments/manage', name: 'enrollments_manage', methods: ['GET'])]
public function manageEnrollments(Request $request, EntityManagerInterface $em): Response
{
    $admin = $this->getUser();
    if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
        return $this->redirectToRoute('show_login');
    }

    // Recherche utilisateur
    $email = $request->query->get('email');

    $userRepo = $em->getRepository(User::class);
    $courseRepo = $em->getRepository(Course::class);

    $users = $email
        ? $userRepo->createQueryBuilder('u')
            ->where('u.email LIKE :email')
            ->setParameter('email', '%' . $email . '%')
            ->getQuery()
            ->getResult()
        : $userRepo->findBy([], ['id' => 'DESC']);

    $courses = $courseRepo->findBy([], ['id' => 'DESC']);

    return $this->render('admin/enrollments_manage.html.twig', [
        'users' => $users,
        'courses' => $courses,
        'email' => $email,
    ]);
}


#[Route('/enrollment/new/{courseId}/{userId}', name: 'enrollment_new', methods: ['POST', 'GET'])]
public function createEnrollment(
    int $courseId,
    int $userId,
    EntityManagerInterface $em
): Response {

    $admin = $this->getUser();
if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
    return $this->redirectToRoute('show_login');
}
    $user = $em->getRepository(User::class)->find($userId);
    $course = $em->getRepository(Course::class)->find($courseId);

    if (!$user || !$course) {
        $this->addFlash('danger', 'Utilisateur ou cours introuvable.');
        return $this->redirectToRoute('admin_enrollments');
    }

    // Vérifier si déjà inscrit
    $existing = $em->getRepository(Enrollment::class)->findOneBy([
        'user'   => $user,
        'course' => $course,
    ]);

    if ($existing) {
        $this->addFlash('info', 'Cet utilisateur est déjà inscrit à ce cours.');
        return $this->redirectToRoute('admin_enrollments');
    }

    // Création enrollment
    $enrollment = new Enrollment();
    $enrollment->setUser($user);
    $enrollment->setCourse($course);

    $em->persist($enrollment);
    $em->flush();

    $this->addFlash('success', "Inscription effectuée pour {$user->getEmail()}.");

    return $this->redirectToRoute('admin_enrollments');
}


#[Route('/enrollment/bulk', name: 'enrollment_bulk', methods: ['POST'])]
public function bulkEnrollment(Request $request, EntityManagerInterface $em): Response
{
    $admin = $this->getUser();
    if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
        return $this->redirectToRoute('show_login');
    }

    $courseId = $request->request->get('courseId');
    $userIds = $request->request->all('userIds');

    if (!$courseId || empty($userIds)) {
        $this->addFlash('danger', 'Données manquantes.');
        return $this->redirectToRoute('admin_enrollments_manage');
    }

    $course = $em->getRepository(Course::class)->find($courseId);
    if (!$course) {
        $this->addFlash('danger', 'Cours introuvable.');
        return $this->redirectToRoute('admin_enrollments_manage');
    }

    $successCount = 0;
    $skipCount = 0;

    foreach ($userIds as $userId) {
        $user = $em->getRepository(User::class)->find($userId);
        if (!$user) continue;

        // Vérifier si déjà inscrit
        $existing = $em->getRepository(Enrollment::class)->findOneBy([
            'user' => $user,
            'course' => $course,
        ]);

        if ($existing) {
            $skipCount++;
            continue;
        }

        $enrollment = new Enrollment();
        $enrollment->setUser($user);
        $enrollment->setCourse($course);
        $em->persist($enrollment);
        $successCount++;
    }

    $em->flush();

    if ($successCount > 0) {
        $this->addFlash('success', "$successCount inscription(s) effectuée(s) avec succès.");
    }
    if ($skipCount > 0) {
        $this->addFlash('info', "$skipCount utilisateur(s) déjà inscrit(s) à ce cours.");
    }

    return $this->redirectToRoute('admin_enrollments');
}

    #[Route('/subscriptions', name: 'subscription_add')]
    public function adminSubscription(SubscriptionRepository $subRepo)
    {
         $admin = $this->getUser();
    if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
        return $this->redirectToRoute('show_login');
    }
    $subscriptions = $subRepo->findAll();

    return $this->render('admin/subscription.html.twig', [
        'subscriptions' => $subscriptions,

    ]);
    }

    #[Route('/subscription/toggle/{id}', name: 'subscription_toggle')]
public function toggleSubscription(
    Subscription $subscription,
    EntityManagerInterface $em
) {
    $admin = $this->getUser();
    if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
        return $this->redirectToRoute('show_login');
    }



$subscription->setIsActive(!$subscription->getIsActive());
    

    $em->persist($subscription);
    $em->flush();

    return $this->redirectToRoute('admin_subscription_add');
}

#[Route('/subscription/create', name: 'subscription_create')]
public function createSubscription(
    Request $request,
    EntityManagerInterface $em,
    UserRepository $userRepo,
    SubscriptionRepository $subRepo
) {
    $admin = $this->getUser();
    if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
        return $this->redirectToRoute('show_login');
    }

    if ($request->isMethod('POST')) {

        $userId = $request->request->get('user_id');
        $type   = $request->request->get('type');
        $start  = $request->request->get('startDate');
        $end    = $request->request->get('endDate');

        $user = $userRepo->find($userId);

        if (!$user) {
            throw $this->createNotFoundException("User introuvable");
        }

        // 🔥 Vérifier si l'utilisateur a déjà une subscription
        $oldSub = $subRepo->findOneBy(['user' => $user]);

        if ($oldSub) {
            $em->remove($oldSub); // Supprime l'ancienne
        }

        // 🔥 Nouvelle subscription
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setType($type);
        $subscription->setStartDate(new \DateTime($start));
        $subscription->setEndDate(new \DateTime($end));
        $subscription->setIsActive(true);

        $em->persist($subscription);
        $em->flush();

        return $this->redirectToRoute('admin_subscription_add');
    }

    // Liste des users pour formulaire
    $users = $userRepo->findAll();

    return $this->render('admin/subscription_create.html.twig', [
        'users' => $users,
    ]);
}

#[Route('/users/import', name: 'user_import')]
public function importUsers(
    Request $request,
    EntityManagerInterface $em,
    UserPasswordHasherInterface $passwordHasher,
    UserRepository $userRepository
): Response {
    $results = [];

    if ($request->isMethod('POST')) {

        $file = $request->files->get('csv_file');

        if (!$file) {
            $this->addFlash('error', 'Aucun fichier envoyé.');
            return $this->redirectToRoute('admin_user_import');
        }

        if ($file->getClientOriginalExtension() !== 'csv') {
            $this->addFlash('error', 'Le fichier doit être un CSV.');
            return $this->redirectToRoute('admin_user_import');
        }

        $handle = fopen($file->getRealPath(), 'r');

        // --- AUTO-DÉTECTION DU SÉPARATEUR ---
        $firstLine = fgets($handle);
        rewind($handle);

        $separator = str_contains($firstLine, ';') ? ';' : ',';

        // --- LECTURE DYNAMIQUE DE L'ENTÊTE ---
        $header = fgetcsv($handle, 0, $separator);
        $header = array_map('trim', $header);

        // Création d’un mapping colonne → index
        $map = array_flip($header);

        // Vérifie que les colonnes obligatoires existent
        $required = ['email','username','password','role'];

        foreach ($required as $col) {
            if (!isset($map[$col])) {
                $this->addFlash('error', "Colonne manquante dans le CSV : $col");
                return $this->redirectToRoute('admin_user_import');
            }
        }

        // --- LECTURE LIGNE PAR LIGNE ---
        while (($data = fgetcsv($handle, 0, $separator)) !== false) {

            $email       = $data[$map['email']]      ?? null;
            $username    = $data[$map['username']]   ?? null;
            $phone       = $data[$map['phone']]      ?? null;
            $country     = $data[$map['country']]    ?? null;
            $city        = $data[$map['city']]       ?? null;
            $postalCode  = $data[$map['postalCode']] ?? null;
            $passwordRaw = $data[$map['password']]   ?? null;
            $roleRaw     = $data[$map['role']]       ?? null;

            if (!$email || !$username) {
                $results[] = "❌ Email ou username manquant → ligne ignorée";
                continue;
            }

            if (!$passwordRaw) {
                $results[] = "❌ Mot de passe manquant pour $email → ligne ignorée";
                continue;
            }

            if (!in_array($roleRaw, ['ROLE_EMPLOYEE', 'ROLE_COMPANY'])) {
                $results[] = "❌ Rôle invalide pour $email → ligne ignorée";
                continue;
            }

            if ($userRepository->findOneBy(['email' => $email])) {
                $results[] = "⚠️ Utilisateur existant : $email → ignoré";
                continue;
            }

            // --- CRÉATION ---
            $user = new User();
            $user->setEmail($email);
            $user->setUsername($username);
            $user->setPhone($phone);
            $user->setCountry($country);
            $user->setCity($city);
            $user->setPostalCode($postalCode);

            $user->setPassword(
                $passwordHasher->hashPassword($user, $passwordRaw)
            );

            $user->setRoles([$roleRaw]);

            $em->persist($user);

            $results[] = "✅ Utilisateur créé : $email";
        }

        fclose($handle);
        $em->flush();

        return $this->render('admin/user_import_results.html.twig', [
            'results' => $results,
        ]);
    }

    return $this->render('admin/user_import.html.twig');
}


}
