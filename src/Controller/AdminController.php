<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Video;
use App\Entity\Course;
use App\Entity\Certificate;
use App\Entity\QuizAttempt;
use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\Category;
use App\Form\AdminCertificateCreateType;
use App\Form\CourseType;
use App\Entity\Enrollment;
use App\Entity\QuizAnswer;
use App\Form\CategoryType;
use App\Entity\QuizQuestion;
use App\Entity\Subscription;
use App\Repository\UserRepository;
use App\Repository\CourseRepository;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CertificateRepository;
use App\Repository\SubscriptionRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

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

    $courseIds = $request->request->all('courseIds');
    $userIds   = $request->request->all('userIds');

    if (empty($courseIds) || empty($userIds)) {
        $this->addFlash('danger', 'Aucun utilisateur ou cours sélectionné.');
        return $this->redirectToRoute('admin_enrollments_manage');
    }

    $courseRepo = $em->getRepository(Course::class);
    $userRepo   = $em->getRepository(User::class);

    $successCount = 0;
    $skipCount = 0;

    foreach ($courseIds as $courseId) {
        $course = $courseRepo->find($courseId);
        if (!$course) continue;

        foreach ($userIds as $userId) {

            $user = $userRepo->find($userId);
            if (!$user) continue;

            // Déjà inscrit ?
            $existing = $em->getRepository(Enrollment::class)->findOneBy([
                'user' => $user,
                'course' => $course,
            ]);

            if ($existing) {
                $skipCount++;
                continue;
            }

            // Création
            $enrollment = new Enrollment();
            $enrollment->setUser($user);
            $enrollment->setCourse($course);
            $em->persist($enrollment);

            $successCount++;
        }
    }

    $em->flush();

    if ($successCount > 0) {
        $this->addFlash('success', "$successCount inscription(s) créée(s) avec succès.");
    }
    if ($skipCount > 0) {
        $this->addFlash('info', "$skipCount inscription(s) déjà existante(s).");
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


    // ---------- ADMIN ARTICLES PAGES -------------
   #[Route('/articles', name: 'articles')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminArticles(ArticleRepository $repo): Response
    {
        return $this->render('admin/admin_list.html.twig', [
            'articles' => $repo->findBy([], ['createdAt' => 'DESC'])
        ]);
    }

    #[Route('/articles/{id}', name: 'article_detail')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminArticleDetail(Article $article): Response
    {
        return $this->render('admin/admin_detail.html.twig', [
            'article' => $article
        ]);
    }

    #[Route('/comments/{id}/delete', name: 'comment_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteComment(Comment $comment, EntityManagerInterface $em): Response
    {
        $articleId = $comment->getArticle()->getId();

        $em->remove($comment);
        $em->flush();

        $this->addFlash('success', 'Comment deleted.');

        return $this->redirectToRoute('admin_article_detail', ['id' => $articleId]);
    }

    #[Route('/articles/{id}/delete', name: 'article_delete_admin', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteArticle(Article $article, EntityManagerInterface $em): Response
    {
        $em->remove($article);
        $em->flush();

        $this->addFlash('success', 'Article deleted.');

        return $this->redirectToRoute('admin_articles');
    }
    #[Route('/categories/new', name: 'category_new')]
    public function newCategorie(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $category = new Category();

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'Category created successfully ✅');

            return $this->redirectToRoute('admin_category_new');
            // ou vers une liste si tu veux plus tard
        }

        return $this->render('admin/category/new.html.twig', [
            'form' => $form,
        ]);
    }
#[Route('/certificate/list', name: 'certificate_list')]
public function certificateList(
    Request $request,
    CertificateRepository $certificateRepo,
    SubscriptionRepository $subscriptionRepo
): Response {
    $preset = $request->query->get('preset'); // week | month | this_month
    $startStr = $request->query->get('start'); // YYYY-MM-DD
    $endStr = $request->query->get('end');     // YYYY-MM-DD

    $now = new \DateTimeImmutable('now');
    $start = null;
    $end = null;

    if ($preset === 'week') {
        $start = $now->modify('-7 days')->setTime(0, 0);
        $end = $now;
    } elseif ($preset === 'month') {
        $start = $now->modify('-30 days')->setTime(0, 0);
        $end = $now;
    } elseif ($preset === 'this_month') {
        $start = $now->modify('first day of this month')->setTime(0, 0);
        $end = $now->modify('last day of this month')->setTime(23, 59, 59);
    } else {
        if ($startStr) {
            $start = \DateTimeImmutable::createFromFormat('Y-m-d', $startStr)?->setTime(0, 0);
        }
        if ($endStr) {
            $end = \DateTimeImmutable::createFromFormat('Y-m-d', $endStr)?->setTime(0, 0);
        }

        // défaut = mois en cours
        if (!$start && !$end) {
            $start = $now->modify('first day of this month')->setTime(0, 0);
            $end = $now->modify('last day of this month')->setTime(23, 59, 59);
        }
    }

     $rows = $certificateRepo->findForListWithPassedAt($start, $end);

    // filtre: sub=1 (avec) / sub=0 (sans) / null (tout)
    $subFilter = $request->query->get('sub'); // "1" | "0" | null
    $courseFilter = $request->query->get('course'); // id du course

    // 1) extraire les userIds
    $userIds = [];
    foreach ($rows as $row) {
        $user = $row['certificate']->getPassed(); // ton User
        if ($user?->getId()) {
            $userIds[] = $user->getId();
        }
    }
    $userIds = array_values(array_unique($userIds));

    // 2) une requête: ids des users qui ont un abo actif
    $activeUserIds = $subscriptionRepo->findUserIdsWithActiveSubscription($userIds);
    $activeMap = array_fill_keys($activeUserIds, true);

    // 3) enrichir + filtrer
$filtered = [];
foreach ($rows as $row) {

    $certificate = $row['certificate'];
    $userId = $certificate->getPassed()?->getId();
    $courseId = $certificate->getCourse()?->getId();

    // filtre cours
    if ($courseFilter && $courseId != $courseFilter) {
        continue;
    }

    $hasSub = $userId ? isset($activeMap[$userId]) : false;

    $row['hasSubscription'] = $hasSub;

    // filtre abonnement
    if ($subFilter === '1' && !$hasSub) {
        continue;
    }
    if ($subFilter === '0' && $hasSub) {
        continue;
    }

    $filtered[] = $row;
}

    // 4) grouper (attention: ton code groupe par timestamp complet)
    $grouped = [];
    foreach ($filtered as $row) {
        $passedAt = $row['passedAt']; // string "2025-12-31 19:47:07"
        $key = $passedAt ? (new \DateTimeImmutable($passedAt))->format('Y-m') : 'unknown';
        $grouped[$key][] = $row;
    }

    if (isset($grouped['unknown'])) {
        $unknown = $grouped['unknown'];
        unset($grouped['unknown']);
        $grouped['unknown'] = $unknown;
    }
    return $this->render('admin/certificate/index.html.twig', [
        'grouped' => $grouped,
        'start' => $start,
        'end' => $end,
        'course' => $courseFilter,
        'preset' => $preset,
        'sub' => $subFilter,
    ]);
}

#[Route('/certificate/create', name: 'certificate_create', methods: ['GET', 'POST'])]
public function certificateCreate(
    Request $request,
    EntityManagerInterface $em,
    CertificateRepository $certificateRepo
): Response {
    $admin = $this->getUser();
    if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
        return $this->redirectToRoute('show_login');
    }

    $form = $this->createForm(AdminCertificateCreateType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $users = $form->get('users')->getData();
        $course = $form->get('course')->getData();
        $durationLabel = trim((string) $form->get('durationLabel')->getData());
        $trainerName = trim((string) $form->get('trainerName')->getData());

        $createdCount = 0;
        $updatedCount = 0;
        $firstCreatedOrUpdatedCertificate = null;
        $quizAttemptRepo = $em->getRepository(QuizAttempt::class);
        $enrollmentRepo = $em->getRepository(Enrollment::class);

        foreach ($users as $user) {
            $certificate = $certificateRepo->findOneBy([
                'passed' => $user,
                'course' => $course,
            ]);

            if (!$certificate) {
                $certificate = new Certificate();
                $certificate->setRef('CERT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)));
                $certificate->setCourse($course);
                $certificate->setPassed($user);
                $em->persist($certificate);
                $createdCount++;
            } else {
                $updatedCount++;
            }

            $certificate->setTitle('Certificate of completion: ' . $course->getTitle());
            $certificate->setDurationLabel($durationLabel);
            $certificate->setTrainerName($trainerName);

            $existingPassedAttempt = $quizAttemptRepo->findOneBy([
                'user' => $user,
                'course' => $course,
                'passed' => true,
            ]);

            if (!$existingPassedAttempt) {
                $quizAttempt = new QuizAttempt();
                $quizAttempt->setUser($user);
                $quizAttempt->setCourse($course);
                $quizAttempt->setScore(100);
                $quizAttempt->setPassed(true);

                $enrollment = $enrollmentRepo->findOneBy([
                    'user' => $user,
                    'course' => $course,
                ], ['createdAt' => 'DESC']);

                if ($enrollment) {
                    $quizAttempt->setEnrollment($enrollment);
                }

                $em->persist($quizAttempt);
            }

            if (!$firstCreatedOrUpdatedCertificate instanceof Certificate) {
                $firstCreatedOrUpdatedCertificate = $certificate;
            }
        }

        $em->flush();

        if (count($users) === 1 && $firstCreatedOrUpdatedCertificate instanceof Certificate) {
            return $this->redirectToRoute('admin_certificate_download', [
                'id' => $firstCreatedOrUpdatedCertificate->getId(),
            ]);
        }

        if ($createdCount > 0) {
            $this->addFlash('success', sprintf('%d certificat(s) créé(s).', $createdCount));
        }
        if ($updatedCount > 0) {
            $this->addFlash('info', sprintf('%d certificat(s) existant(s) mis à jour.', $updatedCount));
        }

        return $this->redirectToRoute('admin_certificate_list');
    }

    return $this->render('admin/certificate/create.html.twig', [
        'form' => $form->createView(),
    ]);
}

#[Route('/certificate/{id}/download', name: 'certificate_download', methods: ['GET'])]
public function certificateDownload(
    Certificate $certificate,
    EntityManagerInterface $em
): Response {
    $admin = $this->getUser();
    if (!in_array('ROLE_ADMIN', $admin->getRoles())) {
        return $this->redirectToRoute('show_login');
    }

    $user = $certificate->getPassed();
    $course = $certificate->getCourse();

    if (!$user || !$course) {
        throw $this->createNotFoundException('Certificat incomplet.');
    }

    $attempt = $em->getRepository(QuizAttempt::class)->findOneBy([
        'user' => $user,
        'course' => $course,
        'passed' => true,
    ], ['createdAt' => 'DESC']);

    $certificateNumber = $certificate->getRef();

    $pdf = new \FPDF('L', 'mm', 'A4');
    $pdf->AddPage();

    $pageWidth = $pdf->GetPageWidth();
    $pageHeight = $pdf->GetPageHeight();

    $background = $this->getParameter('kernel.project_dir') . '/public/build/images/certificate-template.png';
    $pdf->Image($background, 0, 0, $pageWidth, $pageHeight);

    $pdf->SetFont('Arial', 'B', 26);
    $pdf->SetTextColor(0, 0, 0);
    $name = utf8_decode($user->getUsername());
    $nameWidth = $pdf->GetStringWidth($name);
    $x = ($pageWidth - $nameWidth) / 2;
    $y = 95;
    $pdf->SetXY($x, $y);
    $pdf->Cell($nameWidth, 10, $name);

    $pdf->SetFont('Arial', 'B', 18);
    $title = utf8_decode($course->getTitle());
    $maxWidth = $pageWidth * 0.8;
    $titleY = 130;
    $pdf->SetY($titleY);

    $titleWidth = $pdf->GetStringWidth($title);
    if ($titleWidth > $maxWidth) {
        $x = ($pageWidth - $maxWidth) / 2;
        $pdf->SetX($x);
        $pdf->MultiCell($maxWidth, 10, $title, 0, 'C');
    } else {
        $pdf->SetXY(0, $titleY);
        $pdf->Cell($pageWidth, 10, $title, 0, 0, 'C');
    }

    $durationLabel = $certificate->getDurationLabel();
    if (!$durationLabel) {
        $totalDurationSeconds = 0;
        foreach ($course->getVideos() as $video) {
            $totalDurationSeconds += $video->getDuration();
        }
        $durationLabel = round($totalDurationSeconds / 60) . ' min';
    }

    $pdf->SetFont('Arial', '', 14);
    $pdf->SetXY(0, 150);
    $pdf->Cell($pageWidth, 10, utf8_decode('Course Duration : ' . $durationLabel), 0, 0, 'C');

    if ($certificate->getTrainerName()) {
        $pdf->SetXY(0, 160);
        $pdf->Cell($pageWidth, 10, utf8_decode('Trainer : ' . $certificate->getTrainerName()), 0, 0, 'C');
    }

    $date = $attempt?->getCreatedAt() ?? new \DateTime();
    $pdf->SetFont('Arial', '', 14);
    $pdf->SetXY(100, 178);
    $pdf->Cell(40, 10, $date->format('d/m/Y'), 0, 0, 'L');

    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->SetXY($pageWidth - 70, 10);
    $pdf->Cell(60, 10, 'Ref: ' . $certificateNumber, 0, 0, 'R');

    $pdfContent = $pdf->Output('S');

    return new Response(
        $pdfContent,
        200,
        [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => (new ResponseHeaderBag())->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'certificate_' . $certificateNumber . '.pdf'
            ),
        ]
    );
}

}
