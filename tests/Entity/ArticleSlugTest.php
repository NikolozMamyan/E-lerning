<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Article;
use PHPUnit\Framework\TestCase;

final class ArticleSlugTest extends TestCase
{
    public function testHistoricalSlugIsNormalizedWhenGeneratingUrls(): void
    {
        $article = (new Article())->setTitre('Historical article');
        $slug = new \ReflectionProperty(Article::class, 'slug');
        $slug->setValue($article, '651+61');

        self::assertSame('65161', $article->getSlug());
        self::assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $article->getSlug());
    }
}
