<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Course;
use App\Entity\QuizAttempt;
use App\Entity\User;
use App\Repository\EnrollmentRepository;
use App\Repository\QuizAttemptRepository;

final readonly class MobileQuizService
{
    public function __construct(private EnrollmentRepository $enrollments, private QuizAttemptRepository $attempts, private QuizService $quiz) {}

    public function hasAccess(User $user, Course $course): bool
    {
        return $user->hasActiveSubscription() || $this->enrollments->findOneBy(['user' => $user, 'course' => $course]) !== null;
    }

    public function state(User $user, Course $course, string $locale): array
    {
        $locale = in_array($locale, ['en', 'fr', 'it'], true) ? $locale : 'en';
        $canAttempt = $this->quiz->canAttempt($user, $course);
        $attempt = $this->attempts->findLatestPassedForUserAndCourse($user, $course) ?? $this->attempts->findLatestForUserAndCourse($user, $course);
        $languages = ['en'];
        $questions = [];
        foreach ($course->getQuizQuestions() as $question) {
            $multiple = 0;
            $answers = [];
            if ($question->getQuestionFr()) $languages[] = 'fr';
            if ($question->getQuestionIt()) $languages[] = 'it';
            foreach ($question->getAnswers() as $answer) {
                if ($answer->isCorrect()) ++$multiple;
                if ($answer->getTextFr()) $languages[] = 'fr';
                if ($answer->getTextIt()) $languages[] = 'it';
                $answers[] = ['id' => $answer->getId(), 'text' => $answer->getTextForLocale($locale)];
            }
            if ($canAttempt) $questions[] = ['id' => $question->getId(), 'text' => $question->getQuestionForLocale($locale), 'multiple' => $multiple > 1, 'answers' => $answers];
        }
        return ['course' => ['id' => $course->getId(), 'title' => $course->getTitle(), 'image' => $course->getThumb(), 'category' => $course->getCategory()?->getName()],
            'canAttempt' => $canAttempt, 'attempt' => $attempt ? self::attempt($attempt) : null, 'languages' => array_values(array_unique($languages)), 'locale' => $locale, 'questions' => $questions];
    }

    public static function attempt(QuizAttempt $attempt): array
    {
        return ['id' => $attempt->getId(), 'score' => $attempt->getScore(), 'passed' => $attempt->isPassed(), 'createdAt' => $attempt->getCreatedAt()->format(DATE_ATOM)];
    }
}
