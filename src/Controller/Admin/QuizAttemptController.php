<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\CourseRepository;
use App\Repository\QuizAttemptRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\UnicodeString;

#[IsGranted('ROLE_ADMIN')]
final class QuizAttemptController extends AbstractController
{
    private const ITEMS_PER_PAGE = 25;

    private const ALLOWED_SORTS = [
        'newest',
        'oldest',
        'score_asc',
        'score_desc',
    ];

    #[Route('/admin/quizzes', name: 'admin_quiz_attempt_index', methods: ['GET'])]
    public function __invoke(
        Request $request,
        QuizAttemptRepository $quizAttemptRepository,
        CourseRepository $courseRepository,
    ): Response {
        $search = (new UnicodeString(trim((string) $request->query->get('q', ''))))
            ->slice(0, 120)
            ->toString();
        $courseId = $this->queryInt($request, 'course');
        $selectedCourse = $courseId > 0 ? $courseRepository->find($courseId) : null;
        if ($courseId > 0 && $selectedCourse === null) {
            $this->addFlash('warning', 'Le cours sélectionné n’existe pas.');
        }

        $dateFrom = $this->parseDate($request, 'date_from');
        $dateTo = $this->parseDate($request, 'date_to');
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            $this->addFlash('warning', 'La période sélectionnée est invalide et a été ignorée.');
            $dateFrom = null;
            $dateTo = null;
        }

        $sort = (string) $request->query->get('sort', 'newest');
        if (!in_array($sort, self::ALLOWED_SORTS, true)) {
            $sort = 'newest';
        }

        $selectedCourseId = $selectedCourse?->getId();
        $total = $quizAttemptRepository->countFailedForAdmin(
            $search,
            $selectedCourseId,
            $dateFrom,
            $dateTo,
        );
        $pageCount = max(1, (int) ceil($total / self::ITEMS_PER_PAGE));
        $page = min(max(1, $this->queryInt($request, 'page', 1) ?? 1), $pageCount);
        $offset = ($page - 1) * self::ITEMS_PER_PAGE;

        $attempts = $quizAttemptRepository->findFailedForAdmin(
            $search,
            $selectedCourseId,
            $dateFrom,
            $dateTo,
            $sort,
            self::ITEMS_PER_PAGE,
            $offset,
        );

        $filters = [
            'q' => $search,
            'course' => $selectedCourseId,
            'date_from' => $dateFrom?->format('Y-m-d'),
            'date_to' => $dateTo?->format('Y-m-d'),
            'sort' => $sort,
        ];

        return $this->render('admin/quiz_attempt/index.html.twig', [
            'attempts' => $attempts,
            'courses' => $courseRepository->findBy([], ['title' => 'ASC']),
            'filters' => $filters,
            'filterQuery' => array_filter(
                $filters,
                static fn (mixed $value): bool => $value !== null && $value !== '',
            ),
            'page' => $page,
            'pageCount' => $pageCount,
            'total' => $total,
            'firstResult' => $total === 0 ? 0 : $offset + 1,
            'lastResult' => min($offset + count($attempts), $total),
        ]);
    }

    private function parseDate(Request $request, string $parameter): ?\DateTimeImmutable
    {
        $value = trim((string) $request->query->get($parameter, ''));
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            $this->addFlash('warning', 'Une date de filtre invalide a été ignorée.');

            return null;
        }

        return $date;
    }

    private function queryInt(Request $request, string $parameter, ?int $default = null): ?int
    {
        $value = $request->query->filter(
            $parameter,
            $default,
            \FILTER_VALIDATE_INT,
            ['flags' => \FILTER_REQUIRE_SCALAR | \FILTER_NULL_ON_FAILURE],
        );

        return is_int($value) ? $value : $default;
    }
}
