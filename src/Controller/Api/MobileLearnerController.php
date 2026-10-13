<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Course;
use App\Entity\User;
use App\Service\AvatarStorage;
use App\Service\LearnerBillingService;
use App\Service\LearnerCheckoutService;
use App\Service\MobileProfileEditor;
use App\Service\MobileProfileService;
use App\Service\MobileQuizService;
use App\Service\ProfileCoverStorage;
use App\Service\QuizService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mobile', name: 'api_mobile_')]
final class MobileLearnerController extends AbstractController
{
    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function settings(MobileProfileService $profiles): JsonResponse
    {
        return $this->json($profiles->details($this->getUser(), true));
    }

    #[Route('/settings/{section}', name: 'settings_update', methods: ['PATCH'])]
    public function updateSettings(string $section, Request $request, MobileProfileEditor $editor): JsonResponse
    {
        try { $errors = $editor->update($this->getUser(), $section, $request->toArray()); }
        catch (\InvalidArgumentException $exception) { return $this->json(['message' => $exception->getMessage()], 400); }
        return $errors ? $this->json(['message' => $errors[0]['message'], 'errors' => $errors], 422) : $this->json(['success' => true]);
    }

    #[Route('/profile', name: 'profile', methods: ['GET'])]
    public function ownProfile(Request $request, MobileProfileService $profiles): JsonResponse
    {
        return $this->json($profiles->profile($this->getUser(), $this->getUser(), (string) $request->query->get('sort', 'latest')));
    }

    #[Route('/profile/{id<\d+>}', name: 'public_profile', methods: ['GET'])]
    public function profile(User $profileUser, Request $request, MobileProfileService $profiles): JsonResponse
    {
        if (in_array('ROLE_ADMIN', $profileUser->getRoles(), true)) throw $this->createNotFoundException();
        return $this->json($profiles->profile($profileUser, $this->getUser(), (string) $request->query->get('sort', 'latest')));
    }

    #[Route('/profile/{kind}', name: 'profile_image', requirements: ['kind' => 'cover|avatar'], methods: ['POST'])]
    public function image(string $kind, Request $request, ProfileCoverStorage $covers, AvatarStorage $avatars): JsonResponse
    {
        $file = $request->files->get($kind);
        if (!$file instanceof UploadedFile) return $this->json(['message' => 'Select an image.'], 422);
        try { $kind === 'cover' ? $covers->save($this->getUser(), $file) : $avatars->save($this->getUser(), $file); }
        catch (\RuntimeException $exception) { return $this->json(['message' => $exception->getMessage()], 422); }
        return $this->json(['success' => true]);
    }

    #[Route('/billing', name: 'billing', methods: ['GET'])]
    public function billing(LearnerBillingService $billing): JsonResponse
    {
        return $this->json($billing->forUser($this->getUser()));
    }

    #[Route('/checkout/course/{id<\d+>}', name: 'checkout_course', methods: ['POST'])]
    public function checkoutCourse(Course $course, LearnerCheckoutService $checkout): JsonResponse
    {
        try { return $this->json(['url' => $checkout->course($this->getUser(), $course)]); }
        catch (\InvalidArgumentException $exception) { return $this->json(['message' => $exception->getMessage()], 422); }
        catch (\Stripe\Exception\ApiErrorException) { return $this->json(['message' => 'Secure checkout is temporarily unavailable. Please try again.'], 503); }
    }

    #[Route('/checkout/subscription', name: 'checkout_subscription', methods: ['POST'])]
    public function checkoutSubscription(LearnerCheckoutService $checkout): JsonResponse
    {
        try { return $this->json(['url' => $checkout->subscription($this->getUser())]); }
        catch (\InvalidArgumentException $exception) { return $this->json(['message' => $exception->getMessage()], 422); }
        catch (\Stripe\Exception\ApiErrorException) { return $this->json(['message' => 'Secure checkout is temporarily unavailable. Please try again.'], 503); }
    }

    #[Route('/courses/{id<\d+>}/quiz', name: 'quiz', methods: ['GET'])]
    public function quiz(Course $course, Request $request, MobileQuizService $quiz): JsonResponse
    {
        if (!$quiz->hasAccess($this->getUser(), $course)) return $this->json(['message' => 'Course access is required.'], 403);
        return $this->json($quiz->state($this->getUser(), $course, (string) $request->query->get('locale', 'en')));
    }

    #[Route('/courses/{id<\d+>}/quiz', name: 'quiz_submit', methods: ['POST'])]
    public function submitQuiz(Course $course, Request $request, MobileQuizService $mobileQuiz, QuizService $quiz): JsonResponse
    {
        if (!$mobileQuiz->hasAccess($this->getUser(), $course)) return $this->json(['message' => 'Course access is required.'], 403);
        $answers = $request->toArray()['answers'] ?? null;
        if (!is_array($answers)) return $this->json(['message' => 'Invalid answers.'], 422);
        try { $attempt = $quiz->evaluate($this->getUser(), $course, $answers); }
        catch (\Exception $exception) { return $this->json(['message' => $exception->getMessage()], 422); }
        return $this->json(['attempt' => MobileQuizService::attempt($attempt)], 201);
    }
}
