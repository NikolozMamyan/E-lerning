<?php

namespace App\DataFixtures;

use App\Entity\Video;
use App\Entity\Course;
use App\Entity\QuizAnswer;
use App\Entity\CoursePrice;
use App\Entity\QuizQuestion;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // === Cours 1 : Project Management ===
        $course1 = new Course();
        $course1->setTitle("Project Management")
            ->setDescription("Apprenez à gérer un projet de A à Z en utilisant les meilleures pratiques Agile et Waterfall.");

            $price1eur = (new CoursePrice())
    ->setPrice(1999)   // 19,99€
    ->setCurrency("EUR")
    ->setCourse($course1);

// Prix USD
$price1usd = (new CoursePrice())
    ->setPrice(2199)   // 21,99$
    ->setCurrency("USD")
    ->setCourse($course1);

        $video1 = (new Video())
            ->setTitle("Introduction au management de projet")
            ->setDescription("Présentation du cours et des objectifs.")
            ->setDuration(10)
            ->setUrl("https://media.istockphoto.com/id/2185164247/fr/vid%C3%A9o/big-data-et-ia-titre-sur-le-titre-dun-journal.mp4?s=mp4-640x640-is&k=20&c=qHymLqIbtWRIObe8Su3h7dWE8-wMTf_bVis1g12rSJs=")
            ->setCourse($course1);

        $video2 = (new Video())
            ->setTitle("Analyse des risques")
            ->setDescription("Apprenez à identifier et évaluer les risques dans un projet.")
            ->setDuration(10)
            ->setUrl("https://media.istockphoto.com/id/2207202342/fr/vid%C3%A9o/le-boom-du-commerce-%C3%A9lectronique-se-poursuit-avec-la-personnalisation-de-lia.mp4?s=mp4-640x640-is&k=20&c=m_f7OKWUBY6QgUuIc2_DHkYrQ91p2wJ2_eU9EJ28rV0=")
            ->setCourse($course1);

        $manager->persist($course1);
        $manager->persist($price1eur);
        $manager->persist($price1usd);
        $manager->persist($video1);
        $manager->persist($video2);

        // Quiz pour Course 1
        $q1 = new QuizQuestion();
        $q1->setQuestion("Quelle méthode est adaptée pour un projet flexible ?");
        $q1->setCourse($course1);

        $a1 = (new QuizAnswer())->setText("Agile")->setIsCorrect(true)->setQuestion($q1);
        $a2 = (new QuizAnswer())->setText("Waterfall")->setIsCorrect(false)->setQuestion($q1);
        $a3 = (new QuizAnswer())->setText("V-Modèle")->setIsCorrect(false)->setQuestion($q1);
        $a4 = (new QuizAnswer())->setText("Cycle en Y")->setIsCorrect(false)->setQuestion($q1);

        $manager->persist($q1);
        $manager->persist($a1);
        $manager->persist($a2);
        $manager->persist($a3);
        $manager->persist($a4);

        // === Cours 2 : AML / KYC Basics ===
        $course2 = new Course();
        $course2->setTitle("AML / KYC Basics")
            ->setDescription("Comprenez les bases de la conformité AML et KYC, essentielles dans la finance.");

        $video3 = (new Video())
            ->setTitle("Introduction AML")
            ->setDescription("Qu’est-ce que la lutte contre le blanchiment d’argent ?")
            ->setDuration(300)
            ->setUrl("https://media.istockphoto.com/id/1488073043/fr/vid%C3%A9o/a-d%C3%A9chiqueteuse-de-papier-est-d%C3%A9chiqueter-des-documents-confidentiels-ou-classifi%C3%A9s.mp4?s=mp4-640x640-is&k=20&c=qUsTzeP9de3qQHnRzUmQyVWBoZt6k4W6wKTLVMIDkcw=")
            ->setCourse($course2);

        $manager->persist($course2);
        $manager->persist($video3);

        // Quiz pour Course 2
        $q2 = new QuizQuestion();
        $q2->setQuestion("Que signifie KYC ?");
        $q2->setCourse($course2);

        $b1 = (new QuizAnswer())->setText("Know Your Customer")->setIsCorrect(true)->setQuestion($q2);
        $b2 = (new QuizAnswer())->setText("Keep Your Credit")->setIsCorrect(false)->setQuestion($q2);
        $b3 = (new QuizAnswer())->setText("Know Your Company")->setIsCorrect(false)->setQuestion($q2);
        $b4 = (new QuizAnswer())->setText("Keep Your Cash")->setIsCorrect(false)->setQuestion($q2);

        $manager->persist($q2);
        $manager->persist($b1);
        $manager->persist($b2);
        $manager->persist($b3);
        $manager->persist($b4);

        // === Cours 3 : Compliance Training ===
        $course3 = new Course();
        $course3->setTitle("Compliance Training")
            ->setDescription("Formation pratique sur la conformité et la gestion des obligations réglementaires.");

        $video4 = (new Video())
            ->setTitle("Introduction à la conformité")
            ->setDescription("Pourquoi la conformité est essentielle dans les entreprises modernes ?")
            ->setDuration(400)
            ->setUrl("https://media.istockphoto.com/id/1501392576/fr/vid%C3%A9o/documents-confidentiels-copi%C3%A9s-dans-une-photocopieuse.mp4?s=mp4-640x640-is&k=20&c=FDAECajXS04yHmAHVEpxl-K95k5Z-2LNbZbV7amQQJ4=")
            ->setCourse($course3);

        $manager->persist($course3);
        $manager->persist($video4);

        // Quiz pour Course 3
        $q3 = new QuizQuestion();
        $q3->setQuestion("Quel est l'objectif principal de la conformité ?");
        $q3->setCourse($course3);

        $c1 = (new QuizAnswer())->setText("Respecter la loi et les régulations")->setIsCorrect(true)->setQuestion($q3);
        $c2 = (new QuizAnswer())->setText("Augmenter les profits uniquement")->setIsCorrect(false)->setQuestion($q3);
        $c3 = (new QuizAnswer())->setText("Réduire les impôts")->setIsCorrect(false)->setQuestion($q3);
        $c4 = (new QuizAnswer())->setText("Améliorer uniquement la communication interne")->setIsCorrect(false)->setQuestion($q3);

        $manager->persist($q3);
        $manager->persist($c1);
        $manager->persist($c2);
        $manager->persist($c3);
        $manager->persist($c4);

        // Flush en BDD
        $manager->flush();
    }
}
