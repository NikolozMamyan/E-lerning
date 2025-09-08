<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Video;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // === Cours 1 : Project Management ===
        $course1 = new Course();
        $course1->setTitle("Project Management")
            ->setDescription("Apprenez à gérer un projet de A à Z en utilisant les meilleures pratiques Agile et Waterfall.");

        $video1 = (new Video())
            ->setTitle("Introduction au management de projet")
            ->setDescription("Présentation du cours et des objectifs.")
            ->setDuration(120)
            ->setUrl("https://cdn.pixabay.com/video/2019/06/19/24622-347708340_large.mp4")
            ->setCourse($course1);

        $video2 = (new Video())
            ->setTitle("Analyse des risques")
            ->setDescription("Apprenez à identifier et évaluer les risques dans un projet.")
            ->setDuration(600)
            ->setUrl("https://cdn.pixabay.com/video/2019/06/17/24576-347517370_large.mp4")
            ->setCourse($course1);

        $video3 = (new Video())
            ->setTitle("Méthodes Agile")
            ->setDescription("Découvrez Scrum, Kanban et les méthodes Agile pour gérer vos projets.")
            ->setDuration(720)
            ->setUrl("https://cdn.pixabay.com/video/2020/04/07/35708-405917088_large.mp4")
            ->setCourse($course1);

        $manager->persist($course1);
        $manager->persist($video1);
        $manager->persist($video2);
        $manager->persist($video3);

        // === Cours 2 : AML / KYC Basics ===
        $course2 = new Course();
        $course2->setTitle("AML / KYC Basics")
            ->setDescription("Comprenez les bases de la conformité AML et KYC, essentielles dans la finance.");

        $video4 = (new Video())
            ->setTitle("Introduction AML")
            ->setDescription("Qu’est-ce que la lutte contre le blanchiment d’argent ?")
            ->setDuration(300)
            ->setUrl("https://cdn.pixabay.com/video/2022/03/04/108897-686545309_large.mp4")
            ->setCourse($course2);

        $video5 = (new Video())
            ->setTitle("KYC : Know Your Customer")
            ->setDescription("Découvrez les étapes d’identification et vérification des clients.")
            ->setDuration(480)
            ->setUrl("https://cdn.pixabay.com/video/2021/05/31/75445-553232099_large.mp4")
            ->setCourse($course2);

        $manager->persist($course2);
        $manager->persist($video4);
        $manager->persist($video5);

        // === Cours 3 : Compliance Training ===
        $course3 = new Course();
        $course3->setTitle("Compliance Training")
            ->setDescription("Formation pratique sur la conformité et la gestion des obligations réglementaires.");

        $video6 = (new Video())
            ->setTitle("Introduction à la conformité")
            ->setDescription("Pourquoi la conformité est essentielle dans les entreprises modernes ?")
            ->setDuration(400)
            ->setUrl("https://cdn.pixabay.com/video/2020/06/11/40581-423265503_large.mp4")
            ->setCourse($course3);

        $video7 = (new Video())
            ->setTitle("Éthique et responsabilités")
            ->setDescription("Comment respecter les règles tout en restant compétitif ?")
            ->setDuration(540)
            ->setUrl("https://cdn.pixabay.com/video/2020/03/02/32871-392828340_large.mp4")
            ->setCourse($course3);

        $video8 = (new Video())
            ->setTitle("Cas pratiques de conformité")
            ->setDescription("Analyse de situations concrètes et mise en application.")
            ->setDuration(600)
            ->setUrl("https://cdn.pixabay.com/video/2019/10/24/28584-367318951_large.mp4")
            ->setCourse($course3);

        $manager->persist($course3);
        $manager->persist($video6);
        $manager->persist($video7);
        $manager->persist($video8);

        // Flush en BDD
        $manager->flush();
    }
}
