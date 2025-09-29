<?php

namespace App\Controller\Company;

use App\Entity\User;
use App\Entity\Notification;
use App\Entity\Collaboration;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CompanyColabController extends AbstractController
{
    #[Route('company/colab', name: 'company_colab')]
    public function index(): Response {
        $user = $this->getUser();

        // récupère les employés de l’entreprise
        $employees = $user->getCollaborationsAsCompany()
                          ->map(fn($c) => $c->getEmployee());


         $collaborations = $user->getCollaborationsAsCompany();

        return $this->render('company/colab/index.html.twig', [
            'employees' => $employees,
            'collaborations' => $collaborations
        ]);
    }


#[Route('company/colab/{id}/delete', name: 'company_colab_delete', methods: ['GET'])]
public function delete(
    int $id,
    EntityManagerInterface $em
): Response {
    $user = $this->getUser();

    // Vérifie que c'est bien une entreprise
    if (!in_array('ROLE_COMPANY', $user->getRoles())) {
        $this->addFlash('error', 'Access denied.');
        return $this->redirectToRoute('company_colab');
    }

    // Récupérer la collaboration parmi celles de la société connectée
    $collaboration = null;
    foreach ($user->getCollaborationsAsCompany() as $collab) {
        if ($collab->getId() === $id) {
            $collaboration = $collab;
            break;
        }
    }

    if (!$collaboration) {
        $this->addFlash('error', 'Collaboration not found or not allowed.');
        return $this->redirectToRoute('company_colab');
    }

    // Supprimer la collaboration
    $em->remove($collaboration);
    $em->flush();

    $this->addFlash('success', 'Collaborator removed successfully.');

    return $this->redirectToRoute('company_colab');
}


    #[Route('company/colab/add', name: 'company_colab_add', methods: ['POST'])]
    public function addColab(
        Request $request, 
        UserRepository $userRepo,
        NotificationService $notificationService,
        EntityManagerInterface $em
    ): JsonResponse {
        $company = $this->getUser();

        if (!in_array('ROLE_COMPANY', $company->getRoles())) {
            return new JsonResponse(['error' => 'Accès refusé.'], 403);
        }

        $email = $request->request->get('email');
        $employee = $userRepo->findOneBy(['email' => $email]);

        if (!$employee) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé.'], 404);
        }

        if (!in_array('ROLE_EMPLOYEE', $employee->getRoles())) {
            return new JsonResponse(['error' => 'Cet utilisateur ne peut pas être collaborateur.'], 400);
        }

        // Vérifie si la collaboration existe déjà
        foreach ($company->getCollaborationsAsCompany() as $collab) {
            if ($collab->getEmployee() === $employee) {
                return new JsonResponse(['error' => 'Déjà collaborateur.'], 400);
            }
        }

        // Création de la collaboration
        $collaboration = new Collaboration();
        $collaboration->setCompany($company);
        $collaboration->setEmployee($employee);
        $em->persist($collaboration);

        // Création d’une notification
 $notificationService->createEntityNotification(
            $employee,
            '👋 You have a new invitation!',
            $employee,
            "The Company" . ' ' . $company->getCompanyNameFromEmail() . '  ' . "added you to its employees list.",
            Notification::TYPE_INFO,
        );
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Collaborateur ajouté avec succès.'
        ]);
    }
}
