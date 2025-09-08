<?php

namespace App\Controller;

use App\Service\TokenCleaner;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AuthPageController extends AbstractController
{
    #[Route('/login', name: 'show_login', methods: ['GET'])]
    public function login(Request $request, TokenCleaner $cleaner): Response
    {
        $response = new Response();
    
        // 💣 Nettoie le token même si pas authentifié dans "main"
        $cleaner->clearTokenFromRequest($request, $response);
    
        $response->setContent(
            $this->renderView('auth/login.html.twig')
        );
    
        return $response;
    }
    
    #[Route('/register', name: 'show_register', methods: ['GET'])]
    public function showRegister(Request $request, TokenCleaner $cleaner): Response
    {
        $response = new Response();
    
        // 💣 Nettoie le token même si pas authentifié dans "main"
        $cleaner->clearTokenFromRequest($request, $response);
    
        $response->setContent(
            $this->renderView('auth/register.html.twig')
        );
    
        return $response;
    }
    
    
}
