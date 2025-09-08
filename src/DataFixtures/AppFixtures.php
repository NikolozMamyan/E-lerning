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
            ->setDuration(10)
            ->setUrl("https://media.istockphoto.com/id/2185164247/fr/vid%C3%A9o/big-data-et-ia-titre-sur-le-titre-dun-journal.mp4?s=mp4-640x640-is&k=20&c=qHymLqIbtWRIObe8Su3h7dWE8-wMTf_bVis1g12rSJs=")
            ->setCourse($course1);

        $video2 = (new Video())
            ->setTitle("Analyse des risques")
            ->setDescription("Apprenez à identifier et évaluer les risques dans un projet.")
            ->setDuration(10)
            ->setUrl("https://media.istockphoto.com/id/2207202342/fr/vid%C3%A9o/le-boom-du-commerce-%C3%A9lectronique-se-poursuit-avec-la-personnalisation-de-lia.mp4?s=mp4-640x640-is&k=20&c=m_f7OKWUBY6QgUuIc2_DHkYrQ91p2wJ2_eU9EJ28rV0=")
            ->setCourse($course1);

        $video3 = (new Video())
            ->setTitle("Méthodes Agile")
            ->setDescription("Découvrez Scrum, Kanban et les méthodes Agile pour gérer vos projets.")
            ->setDuration(20)
            ->setUrl("https://media.istockphoto.com/id/1012412538/fr/vid%C3%A9o/43e-joyeux-anniversaire-texte-greeting-et-voeux-carte-made-de-glitter-particules-dor-feu.mp4?s=mp4-640x640-is&k=20&c=GzBV9qsx-PLsWUrzXmN5ZmehM4qhPwdUZAimTrVwF0c=")
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
            ->setUrl("https://media.istockphoto.com/id/1488073043/fr/vid%C3%A9o/a-d%C3%A9chiqueteuse-de-papier-est-d%C3%A9chiqueter-des-documents-confidentiels-ou-classifi%C3%A9s.mp4?s=mp4-640x640-is&k=20&c=qUsTzeP9de3qQHnRzUmQyVWBoZt6k4W6wKTLVMIDkcw=")
            ->setCourse($course2);

        $video5 = (new Video())
            ->setTitle("KYC : Know Your Customer")
            ->setDescription("Découvrez les étapes d’identification et vérification des clients.")
            ->setDuration(480)
            ->setUrl("https://media.istockphoto.com/id/2209113611/fr/vid%C3%A9o/titres-de-journaux-li%C3%A9s-%C3%A0-l%C3%A9conomie.mp4?s=mp4-640x640-is&k=20&c=6g5n2Z9nlovWF87MrCLVgV_ArQ1tTbatpyG5NSWCV6g=")
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
            ->setUrl("https://media.istockphoto.com/id/1501392576/fr/vid%C3%A9o/documents-confidentiels-copi%C3%A9s-dans-une-photocopieuse.mp4?s=mp4-640x640-is&k=20&c=FDAECajXS04yHmAHVEpxl-K95k5Z-2LNbZbV7amQQJ4=")
            ->setCourse($course3);

        $video7 = (new Video())
            ->setTitle("Éthique et responsabilités")
            ->setDescription("Comment respecter les règles tout en restant compétitif ?")
            ->setDuration(540)
            ->setUrl("https://media.istockphoto.com/id/2207149397/fr/vid%C3%A9o/interfaces-cerveau-ordinateur-titres-de-journaux.mp4?s=mp4-640x640-is&k=20&c=iXKEMgltr3BhL3Ps0wlcRJp5jJSZDVV0FkXzxYa_QWo=")
            ->setCourse($course3);

        $video8 = (new Video())
            ->setTitle("Cas pratiques de conformité")
            ->setDescription("Analyse de situations concrètes et mise en application.")
            ->setDuration(600)
            ->setUrl("https://media.istockphoto.com/id/1044014008/fr/vid%C3%A9o/application-na-pas-de-notification-davertissement-g%C3%A9n%C3%A9r%C3%A9e-sur-digital-security-alert.mp4?s=mp4-640x640-is&k=20&c=xreYzXOeZWuG8FQmjgTS-j_FeabKGniGIC0p9O9iUDE=")
            ->setCourse($course3);

        $manager->persist($course3);
        $manager->persist($video6);
        $manager->persist($video7);
        $manager->persist($video8);

        // Flush en BDD
        $manager->flush();
    }
}
