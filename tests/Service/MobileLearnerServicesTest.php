<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\QuizAnswer;
use App\Entity\QuizQuestion;
use App\Entity\User;
use App\Repository\ArticleRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\QuizAttemptRepository;
use App\Repository\SubscriptionRepository;
use App\Service\LearnerArticlePresenter;
use App\Service\LearnerBillingService;
use App\Service\LearnerCheckoutService;
use App\Service\MobileProfileEditor;
use App\Service\MobileProfileService;
use App\Service\MobileQuizService;
use App\Service\PublicCommunityFeedPresenter;
use App\Service\QuizService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MobileLearnerServicesTest extends TestCase
{
    public function testPublicProfileDoesNotExposePrivateContactOrBillingData(): void
    {
        $user = (new User())->setUsername('Member')->setEmail('private@example.com')->setPhone('1234')->setTaxId('VAT-secret')->setPostalCode('12345');
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $presenter = new LearnerArticlePresenter(new PublicCommunityFeedPresenter($urls, $this->createMock(HtmlSanitizerInterface::class)));
        $service = new MobileProfileService($this->createMock(ArticleRepository::class), $this->createMock(QuizAttemptRepository::class), $presenter);
        $public = $service->details($user, false);
        self::assertNull($public['email']); self::assertNull($public['phone']);
        self::assertNull($public['company']['taxId']); self::assertNull($public['location']['postalCode']);
        self::assertSame('private@example.com', $service->details($user, true)['email']);
    }

    public function testQuizProvidesChoiceTypeAndTranslationsWithoutCorrectAnswers(): void
    {
        $course = (new Course())->setTitle('Training');
        $question = (new QuizQuestion())->setQuestion('English')->setQuestionFr('Français');
        $question->addAnswer((new QuizAnswer())->setText('One')->setTextFr('Un')->setIsCorrect(true));
        $question->addAnswer((new QuizAnswer())->setText('Two')->setIsCorrect(true));
        $course->addQuizQuestion($question);
        $quiz = $this->createMock(QuizService::class);
        $quiz->method('canAttempt')->willReturn(true);
        $service = new MobileQuizService($this->createMock(EnrollmentRepository::class), $this->createMock(QuizAttemptRepository::class), $quiz);
        $state = $service->state(new User(), $course, 'fr');
        self::assertSame('Français', $state['questions'][0]['text']);
        self::assertTrue($state['questions'][0]['multiple']);
        self::assertSame(['id', 'text'], array_keys($state['questions'][0]['answers'][0]));
        self::assertContains('fr', $state['languages']);
    }

    public function testLockedQuizDoesNotReturnQuestions(): void
    {
        $quiz = $this->createMock(QuizService::class); $quiz->method('canAttempt')->willReturn(false);
        $service = new MobileQuizService($this->createMock(EnrollmentRepository::class), $this->createMock(QuizAttemptRepository::class), $quiz);
        $course = (new Course())->addQuizQuestion((new QuizQuestion())->setQuestion('Private quiz'));
        self::assertSame([], $service->state(new User(), $course, 'en')['questions']);
    }

    public function testBillingKeepsInvoiceCurrencyAndExcludesForeignAmountsFromEurSpend(): void
    {
        $user = new User(); $course = (new Course())->setTitle('Training');
        $eur = (new Enrollment())->setUser($user)->setCourse($course)->setInvoiceAmountCents(5000)->setInvoiceCurrency('EUR');
        $usd = (new Enrollment())->setUser($user)->setCourse($course)->setInvoiceAmountCents(7500)->setInvoiceCurrency('USD');
        $enrollments = $this->createMock(EnrollmentRepository::class); $enrollments->method('findByUserWithCourse')->willReturn([$eur, $usd]);
        $urls = $this->createMock(UrlGeneratorInterface::class); $urls->method('generate')->willReturn('/invoice');
        $billing = (new LearnerBillingService($enrollments, $this->createMock(SubscriptionRepository::class), $urls))->forUser($user);
        self::assertSame(5000, $billing['totalSpendEurCents']); self::assertSame('USD', $billing['records'][1]['currency']);
    }

    public function testInvalidProfileNameDoesNotFlushChanges(): void
    {
        $em = $this->createMock(EntityManagerInterface::class); $em->expects(self::never())->method('flush');
        $editor = new MobileProfileEditor($this->createMock(FormFactoryInterface::class), $em);
        self::assertNotEmpty($editor->update(new User(), 'personal', ['username' => '']));
    }

    public function testCourseWithoutPriceDoesNotStartCheckout(): void
    {
        $service = new LearnerCheckoutService('unused', $this->createMock(UrlGeneratorInterface::class));
        $this->expectException(\InvalidArgumentException::class);
        $service->course(new User(), new Course());
    }
}
