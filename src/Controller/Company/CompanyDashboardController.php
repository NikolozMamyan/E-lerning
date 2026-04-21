<?php

namespace App\Controller\Company;

use App\Entity\User;
use App\Form\PersonalInfoType;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\ProgressRepository;
use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CompanyDashboardController extends AbstractController
{
    #[Route('company/dashboard', name: 'company_dashboard')]
    public function index(
        CourseRepository $courseRepo,
        ProgressRepository $progressRepo,
        QuizAttemptRepository $quizAttemptRepo,
        EnrollmentRepository $enrollmentRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        $courses = $courseRepo->findAll();
        $latestCourses = $courseRepo->findBy([], ['updatedAt' => 'DESC'], 3);

        $progressData = [];
        if ($user) {
            foreach ($courses as $course) {
                $videos = $course->getVideos();
                $totalVideos = count($videos);
                $percent = 0;

                if ($totalVideos > 0) {
                    $completedPercent = 0;
                    foreach ($videos as $video) {
                        $progress = $progressRepo->findOneBy(['user' => $user, 'video' => $video]);
                        if ($progress) {
                            $watched = $progress->getWatchedSeconds();
                            $duration = $video->getDuration();
                            if ($duration > 0) {
                                $completedPercent += min(($watched / $duration) * 100, 100);
                            }
                        }
                    }
                    $percent = round($completedPercent / $totalVideos, 2);
                }

                $progressData[$course->getId()] = $percent;
            }
        }

        $enrollments = $user ? $enrollmentRepo->findByUserWithCourse($user) : [];

        $collaborations = $user->getCollaborationsAsCompany();
        $employees = $collaborations->map(fn($c) => $c->getEmployee());

        $collaboratorsProgress = [];

        foreach ($collaborations as $collab) {
            $employee = $collab->getEmployee();
            $employeeProgress = [];

            foreach ($courses as $course) {
                $videos = $course->getVideos();
                $totalVideos = count($videos);
                $percent = 0;

                if ($totalVideos > 0) {
                    $completedPercent = 0;
                    foreach ($videos as $video) {
                        $progress = $progressRepo->findOneBy([
                            'user' => $employee,
                            'video' => $video,
                        ]);

                        if ($progress) {
                            $watched = $progress->getWatchedSeconds();
                            $duration = $video->getDuration();
                            if ($duration > 0) {
                                $completedPercent += min(($watched / $duration) * 100, 100);
                            }
                        }
                    }

                    $percent = round($completedPercent / $totalVideos, 2);
                }

                $employeeProgress[$course->getId()] = $percent;
            }

            $collaboratorsProgress[] = [
                'employee' => $employee,
                'progress' => $employeeProgress,
            ];
        }

        return $this->render('company/dashboard/index.html.twig', [
            'courses' => $courses,
            'latestCourses' => $latestCourses,
            'progressData' => $progressData,
            'enrollments' => $enrollments,
            'employees' => $employees,
            'collaborations' => $collaborations,
            'collaboratorsProgress' => $collaboratorsProgress,
        ]);
    }

    #[Route('company/team-tracking', name: 'company_team_tracking')]
    public function teamTracking(
        CourseRepository $courseRepo,
        ProgressRepository $progressRepo,
        QuizAttemptRepository $attemptRepo,
        EnrollmentRepository $enrollmentRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in as a company.');
        }

        $courses = $courseRepo->findAll();
        $enrollments = $enrollmentRepo->findByUserWithCourse($user);

        $collaborations = $user->getCollaborationsAsCompany();
        $employees = $collaborations->map(fn($c) => $c->getEmployee());

        $collaboratorsProgress = [];

        foreach ($collaborations as $collab) {
            $employee = $collab->getEmployee();
            $employeeProgress = [];

            foreach ($courses as $course) {
                $videos = $course->getVideos();
                $totalVideos = count($videos);
                $percent = 0;

                if ($totalVideos > 0) {
                    $completedPercent = 0;

                    foreach ($videos as $video) {
                        $progress = $progressRepo->findOneBy([
                            'user' => $employee,
                            'video' => $video,
                        ]);

                        if ($progress) {
                            $watched = $progress->getWatchedSeconds();
                            $duration = $video->getDuration();

                            if ($duration > 0) {
                                $completedPercent += min(($watched / $duration) * 100, 100);
                            }
                        }
                    }

                    $percent = round($completedPercent / $totalVideos, 2);
                }

                $employeeProgress[$course->getId()] = $percent;
            }

            $average = 0;
            if (count($employeeProgress) > 0) {
                $average = round(array_sum($employeeProgress) / count($employeeProgress), 2);
            }

            $collaboratorsProgress[] = [
                'employee' => $employee,
                'progress' => $employeeProgress,
                'average' => $average,
            ];
        }

        $employeeIds = [];
        foreach ($collaborations as $collab) {
            $employeeIds[] = $collab->getEmployee()->getId();
        }

        $attempts = !empty($employeeIds) ? $attemptRepo->findByUserIds($employeeIds) : [];
        $courseResults = [];

        foreach ($attempts as $attempt) {
            $attemptUser = $attempt->getUser();
            $attemptCourse = $attempt->getCourse();

            if (!$attemptUser || !$attemptCourse) {
                continue;
            }

            $courseResults[] = [
                'userId' => $attemptUser->getId(),
                'userName' => $attemptUser->getUsername(),
                'userEmail' => $attemptUser->getEmail(),
                'courseId' => $attemptCourse->getId(),
                'courseTitle' => $attemptCourse->getTitle(),
                'score' => $attempt->getScore(),
                'passed' => $attempt->isPassed(),
                'attempted' => true,
                'attemptedAt' => $attempt->getCreatedAt()
                    ? $attempt->getCreatedAt()->format('Y-m-d H:i:s')
                    : null,
            ];
        }

        return $this->render('company/dashboard/team_tracking.html.twig', [
            'courses' => $courses,
            'enrollments' => $enrollments,
            'employees' => $employees,
            'collaborations' => $collaborations,
            'collaboratorsProgress' => $collaboratorsProgress,
            'courseResults' => $courseResults,
        ]);
    }

    #[Route('company/employee/{id}/edit', name: 'company_employee_edit', methods: ['GET', 'POST'])]
    public function editEmployee(
        User $employee,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $company = $this->getUser();

        $isLinked = false;
        foreach ($company->getCollaborationsAsCompany() as $collab) {
            if ($collab->getEmployee()->getId() === $employee->getId()) {
                $isLinked = true;
                break;
            }
        }

        if (!$isLinked) {
            throw $this->createAccessDeniedException('Cet employé ne vous appartient pas.');
        }

        $form = $this->createForm(PersonalInfoType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Employé mis à jour avec succès',
                    'html' => $this->renderView('company/dashboard/_employee_row.html.twig', [
                        'employee' => $employee,
                    ]),
                ]);
            }

            $this->addFlash('success', 'Employé mis à jour avec succès');

            return $this->redirectToRoute('company_dashboard');
        }

        return $this->render('company/dashboard/edit_employee.html.twig', [
            'form' => $form->createView(),
            'employee' => $employee,
        ]);
    }
}
