<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Article;
use App\Entity\User;
use App\Service\ArticlePublicationNotifier;
use App\Service\MailerService;
use App\Service\NotificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ArticlePublicationNotifierTest extends TestCase
{
    public function testEmailIsDeferredUntilAfterResponseAndSentOnlyOnce(): void
    {
        $article = (new Article())->setTitre('Published')->setDescription('Body')->setAuthor(new User());
        $notifications = $this->createMock(NotificationService::class);
        $notifications->expects(self::once())->method('createEntityNotification');
        $sent = [];
        $mailer = $this->createMock(MailerService::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(function (...$arguments) use (&$sent): void { $sent[] = $arguments; });
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/articles/published');
        $service = new ArticlePublicationNotifier($notifications, $mailer, $urls, $this->createMock(LoggerInterface::class));
        $service->notify($article);
        self::assertSame([], $sent);
        $service->sendPendingEmails();
        self::assertSame('emails/new_article.html.twig', $sent[0][2]);
        self::assertSame($article, $sent[0][3]['article']);
        $service->sendPendingEmails();
    }

    public function testMailFailureDoesNotTurnPublicationIntoAnError(): void
    {
        $article = (new Article())->setTitre('Published')->setAuthor(new User());
        $notifications = $this->createMock(NotificationService::class);
        $notifications->expects(self::once())->method('createEntityNotification');
        $mailer = $this->createMock(MailerService::class);
        $mailer->expects(self::once())->method('send')->willThrowException(new \RuntimeException('SMTP unavailable'));
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/articles/published');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Published article email failed.', self::anything());
        $service = new ArticlePublicationNotifier($notifications, $mailer, $urls, $logger);
        $service->notify($article);
        $service->sendPendingEmails();
    }

    public function testNotificationFailureDoesNotAbortAnAlreadyPublishedArticle(): void
    {
        $article = (new Article())->setTitre('Published')->setAuthor(new User());
        $notifications = $this->createMock(NotificationService::class);
        $notifications->method('createEntityNotification')->willThrowException(new \RuntimeException('Notification unavailable'));
        $mailer = $this->createMock(MailerService::class);
        $mailer->expects(self::once())->method('send');
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/articles/published');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Published article notification failed.', self::anything());
        $service = new ArticlePublicationNotifier($notifications, $mailer, $urls, $logger);
        $service->notify($article);
        $service->sendPendingEmails();
    }
}
