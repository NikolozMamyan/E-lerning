<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ArticlePublicationNotifier
{
    private array $pendingEmails = [];

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly MailerService $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly LoggerInterface $logger,
    ) {}

    public function notify(Article $article): void
    {
        try {
            $this->notifications->createEntityNotification(
                user: $article->getAuthor(),
                title: 'Your article has been published!',
                relatedEntity: $article,
                message: "Your article {$article->getTitre()} is now visible in the feed.",
                type: 'success',
                actionUrl: $this->urls->generate('app_public_article', ['id' => $article->getId(), 'slug' => $article->getSlug()]),
                icon: 'fa-solid fa-newspaper',
            );
        } catch (\Throwable $error) {
            $this->logger->error('Published article notification failed.', ['articleId' => $article->getId(), 'exception' => $error]);
        }
        $this->pendingEmails[] = $article;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function sendPendingEmails(): void
    {
        $pending = $this->pendingEmails;
        $this->pendingEmails = [];
        foreach ($pending as $article) {
            try {
                $this->mailer->send(
                    to: 'pruffin@les-consultants.lu',
                    subject: 'New article published on your platform',
                    template: 'emails/new_article.html.twig',
                    context: ['author' => $article->getAuthor(), 'article' => $article, 'description' => $article->getDescription()],
                );
            } catch (\Throwable $error) {
                $this->logger->error('Published article email failed.', ['articleId' => $article->getId(), 'exception' => $error]);
            }
        }
    }
}
