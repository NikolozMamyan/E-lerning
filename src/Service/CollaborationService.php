<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\Collaboration;
use Doctrine\ORM\EntityManagerInterface;

class CollaborationService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function addCollaboration(User $company, User $employee): Collaboration
    {
        // Vérifie que l’utilisateur est bien une Company
        if (!in_array('ROLE_COMPANY', $company->getRoles())) {
            throw new \Exception("Seuls les utilisateurs avec ROLE_COMPANY peuvent ajouter des collaborateurs.");
        }

        // Vérifie que le collaborateur est bien un Employee
        if (!in_array('ROLE_EMPLOYEE', $employee->getRoles())) {
            throw new \Exception("Seuls les utilisateurs avec ROLE_EMPLOYEE peuvent être ajoutés comme collaborateurs.");
        }

        $collaboration = new Collaboration();
        $collaboration->setCompany($company);
        $collaboration->setEmployee($employee);

        $this->em->persist($collaboration);
        $this->em->flush();

        return $collaboration;
    }
}
