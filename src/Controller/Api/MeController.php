<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api', name: 'api_')]
class MeController extends AbstractController
{
    #[Route('/profile', name: 'profile_get', methods: ['GET'])]
    public function profile(UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $avatar = $user->getAvatar();
        $avatarUrl = null;

        // ✅ Si tu exposes tes uploads via /uploads (public/uploads)
        if ($avatar) {
            // url relative:
            $avatarUrl = '/uploads/avatars/' . $avatar;

            // si tu veux une URL absolue (recommandé mobile):
            // $avatarUrl = $urlGenerator->generate('uploads_avatar', ['filename' => $avatar], UrlGeneratorInterface::ABSOLUTE_URL);
            // (ou juste: $this->generateUrl('...', ..., ABSOLUTE_URL) si tu as une route)
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'phone' => $user->getPhone(),
            'bio' => $user->getBio(),
            'avatar' => $avatar,
            'avatarUrl' => $avatarUrl,

            'location' => [
                'country' => $user->getCountry(),
                'city' => $user->getCity(),
                'postalCode' => $user->getPostalCode(),
            ],

            'company' => [
                'taxId' => $user->getTaxId(),
            ],

            'education' => [
                'bachelorDegree' => $user->getBachelorDegree(),
                'masterDegree' => $user->getMasterDegree(),
            ],
        ]);
    }

    #[Route('/profile', name: 'profile_patch', methods: ['PATCH'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['message' => 'Invalid JSON'], 400);
        }

        // ✅ champs simples
        if (array_key_exists('username', $data)) $user->setUsername((string) $data['username']);
        if (array_key_exists('phone', $data))    $user->setPhone($data['phone'] !== null ? (string) $data['phone'] : null);
        if (array_key_exists('bio', $data))      $user->setBio($data['bio'] !== null ? (string) $data['bio'] : null);

        // ✅ location
        if (isset($data['location']) && is_array($data['location'])) {
            $loc = $data['location'];
            if (array_key_exists('country', $loc))    $user->setCountry($loc['country'] !== null ? (string) $loc['country'] : null);
            if (array_key_exists('city', $loc))       $user->setCity($loc['city'] !== null ? (string) $loc['city'] : null);
            if (array_key_exists('postalCode', $loc)) $user->setPostalCode($loc['postalCode'] !== null ? (string) $loc['postalCode'] : null);
        }

        // ✅ company
        if (isset($data['company']) && is_array($data['company'])) {
            $c = $data['company'];
            if (array_key_exists('taxId', $c)) $user->setTaxId($c['taxId'] !== null ? (string) $c['taxId'] : null);
        }

        // ✅ education
        if (isset($data['education']) && is_array($data['education'])) {
            $edu = $data['education'];
            if (array_key_exists('bachelorDegree', $edu)) $user->setBachelorDegree($edu['bachelorDegree'] !== null ? (string) $edu['bachelorDegree'] : null);
            if (array_key_exists('masterDegree', $edu))   $user->setMasterDegree($edu['masterDegree'] !== null ? (string) $edu['masterDegree'] : null);
        }

        // ✅ validation Symfony (si tu as des Assert sur ton entity)
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $out = [];
            foreach ($errors as $err) {
                $out[] = [
                    'field' => $err->getPropertyPath(),
                    'message' => $err->getMessage(),
                ];
            }
            return $this->json(['message' => 'Validation failed', 'errors' => $out], 422);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profile updated',
        ]);
    }

    #[Route('/profile/avatar', name: 'profile_avatar', methods: ['POST'])]
    public function uploadAvatar(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        // Flutter => multipart/form-data, champ "avatar"
        $file = $request->files->get('avatar');
        if (!$file) {
            return $this->json(['message' => 'No file uploaded (avatar)'], 400);
        }

        // (optionnel) mini sécurité
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowed, true)) {
            return $this->json(['message' => 'Invalid file type'], 415);
        }

        $newFilename = uniqid('avatar_', true) . '.' . ($file->guessExtension() ?: 'jpg');

        try {
            $file->move($this->getParameter('uploads_directory'), $newFilename);
        } catch (FileException $e) {
            return $this->json(['message' => 'Upload failed'], 500);
        }

        // (optionnel) supprimer l'ancien avatar si tu veux éviter d'accumuler des fichiers
        // $old = $user->getAvatar();
        // if ($old) { @unlink($this->getParameter('uploads_directory') . '/' . $old); }

        $user->setAvatar($newFilename);
        $em->flush();

        return $this->json([
            'success' => true,
            'avatar' => $newFilename,
            'avatarUrl' => '/uploads/avatars/' . $newFilename,
        ]);
    }
}