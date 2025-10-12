<?php

namespace App\Controller;

use App\Service\TokenCleaner;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AuthPageController extends AbstractController
{
    #[Route('/login', name: 'show_login', methods: ['GET'])]
    public function login(Request $request, TokenCleaner $cleaner, Security $security): Response
    {
        $response = new Response();
    
        // 💣 Nettoie le token même si pas authentifié dans "main"
        $cleaner->clearTokenFromRequest($request, $response);

        $user = $this->getUser(); 
        if($user) {
              $security->logout(false);
        }
    
    
        $response->setContent(
            $this->renderView('auth/login.html.twig')
        );
    
        return $response;
    }
    
    #[Route('/register', name: 'show_register', methods: ['GET'])]
    public function showRegister(Request $request, TokenCleaner $cleaner, Security $security): Response
    {
        $response = new Response();
    
        // 💣 Nettoie le token même si pas authentifié dans "main"
        $cleaner->clearTokenFromRequest($request, $response);
        $user = $this->getUser(); 
        if($user) {
              $security->logout(false);
        }
    
        $response->setContent(
            $this->renderView('auth/register.html.twig')
        );
    
        return $response;
    }


        #[Route('/', name: 'app_welcome', methods: ['GET'])]
    public function welcome(Request $request, TokenCleaner $cleaner): Response
    {

    
       return new RedirectResponse('/login');
    }
    
    
}
