<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api', name: 'api_')]
class AuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        Security $security
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');
        $displayName = trim($data['displayName'] ?? '');
        $firstName = isset($data['firstName']) ? trim((string) $data['firstName']) : null;
        $lastName = isset($data['lastName']) ? trim((string) $data['lastName']) : null;
        $birthDateParam = isset($data['birthDate']) ? trim((string) $data['birthDate']) : null;
        $birthDate = null;
        if ($birthDateParam !== '') {
            try {
                $birthDate = new DateTimeImmutable($birthDateParam);
            } catch (\Throwable $e) {
                return $this->json(['message' => 'Date de naissance invalide'], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($email === '' || $password === '') {
            return $this->json(['message' => 'Email et mot de passe sont requis'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['message' => 'Email invalide'], Response::HTTP_BAD_REQUEST);
        }

        if ($userRepository->findOneBy(['email' => $email])) {
            return $this->json(['message' => 'Un compte existe deja avec cet email'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($displayName !== '' ? $displayName : explode('@', $email)[0]);
        $user->setFirstName($firstName ?: null);
        $user->setLastName($lastName ?: null);
        $user->setBirthDate($birthDate);
        $hashedPassword = $passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        $security->login($user);

        return $this->json([
            'message' => 'Compte cree',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'birthDate' => $user->getBirthDate()?->format(DateTimeImmutable::ATOM),
                'roles' => $user->getRoles(),
                'createdAt' => $user->getCreatedAt()->format(DateTimeImmutable::ATOM),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        Security $security
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->json(['message' => 'Email et mot de passe sont requis'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['message' => 'Identifiants invalides'], Response::HTTP_UNAUTHORIZED);
        }
        if (in_array('ROLE_SUSPENDED', $user->getRoles(), true)) {
            return $this->json(['message' => 'Votre compte est suspendu, veuillez contacter un administrateur'], Response::HTTP_FORBIDDEN);
        }

        $security->login($user);

        return $this->json([
            'message' => 'Connexion reussie',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'birthDate' => $user->getBirthDate()?->format(DateTimeImmutable::ATOM),
                'roles' => $user->getRoles(),
                'createdAt' => $user->getCreatedAt()->format(DateTimeImmutable::ATOM),
            ],
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request, TokenStorageInterface $tokenStorage): JsonResponse
    {
        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        return $this->json(['message' => 'Deconnexion effectuee']);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifie'], Response::HTTP_UNAUTHORIZED);
        }
        if (in_array('ROLE_SUSPENDED', $user->getRoles(), true)) {
            return $this->json(['message' => 'Votre compte est suspendu, veuillez contacter un administrateur'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'birthDate' => $user->getBirthDate()?->format(DateTimeImmutable::ATOM),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(DateTimeImmutable::ATOM),
        ]);
    }

    #[Route('/profile', name: 'profile_update', methods: ['PATCH'])]
    public function updateProfile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifie'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $displayName = isset($data['displayName']) ? trim((string) $data['displayName']) : null;
        $firstName = isset($data['firstName']) ? trim((string) $data['firstName']) : null;
        $lastName = isset($data['lastName']) ? trim((string) $data['lastName']) : null;
        $birthDateParam = isset($data['birthDate']) ? trim((string) $data['birthDate']) : null;
        $birthDate = null;
        if ($birthDateParam !== null && $birthDateParam !== '') {
            try {
                $birthDate = new DateTimeImmutable($birthDateParam);
            } catch (\Throwable $e) {
                return $this->json(['message' => 'Date de naissance invalide'], Response::HTTP_BAD_REQUEST);
            }
        }
        $currentPassword = isset($data['currentPassword']) ? (string) $data['currentPassword'] : null;
        $newPassword = isset($data['newPassword']) ? (string) $data['newPassword'] : null;

        $touched = false;

        if ($displayName !== null && $displayName !== '') {
            $user->setDisplayName($displayName);
            $touched = true;
        }
        if ($firstName !== null) {
            $user->setFirstName($firstName !== '' ? $firstName : null);
            $touched = true;
        }
        if ($lastName !== null) {
            $user->setLastName($lastName !== '' ? $lastName : null);
            $touched = true;
        }
        if ($birthDateParam !== null) {
            $user->setBirthDate($birthDate);
            $touched = true;
        }

        if ($newPassword !== null && $newPassword !== '') {
            if (!$currentPassword || !$passwordHasher->isPasswordValid($user, $currentPassword)) {
                return $this->json(['message' => 'Mot de passe actuel invalide'], Response::HTTP_BAD_REQUEST);
            }
            if (strlen($newPassword) < 8) {
                return $this->json(['message' => 'Nouveau mot de passe trop court (8 caracteres min)'], Response::HTTP_BAD_REQUEST);
            }
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $touched = true;
        }

        if ($touched) {
            $entityManager->flush();
        }

        return $this->json([
            'message' => 'Profil mis a jour',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'birthDate' => $user->getBirthDate()?->format(DateTimeImmutable::ATOM),
                'roles' => $user->getRoles(),
                'createdAt' => $user->getCreatedAt()->format(DateTimeImmutable::ATOM),
            ],
        ]);
    }

    #[Route('/reset/request', name: 'reset_request', methods: ['POST'])]
    public function requestReset(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');
        if ($email === '') {
            return $this->json(['message' => 'Email requis'], Response::HTTP_BAD_REQUEST);
        }
        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json(['message' => 'Si un compte existe, un lien a ete envoye']);
        }
        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt((new DateTimeImmutable())->modify('+1 hour'));
        $entityManager->flush();

        return $this->json([
            'message' => 'Lien de reinitialisation genere',
            'resetToken' => $token,
            'expiresAt' => $user->getResetTokenExpiresAt()?->format(DateTimeImmutable::ATOM),
        ]);
    }

    #[Route('/reset/confirm', name: 'reset_confirm', methods: ['POST'])]
    public function confirmReset(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $token = (string) ($data['token'] ?? '');
        $newPassword = (string) ($data['newPassword'] ?? '');

        if ($token === '' || $newPassword === '') {
            return $this->json(['message' => 'Token et nouveau mot de passe requis'], Response::HTTP_BAD_REQUEST);
        }
        if (strlen($newPassword) < 8) {
            return $this->json(['message' => 'Mot de passe trop court (8 caracteres min)'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['resetToken' => $token]);
        if (!$user || !$user->getResetTokenExpiresAt() || $user->getResetTokenExpiresAt() < new DateTimeImmutable()) {
            return $this->json(['message' => 'Token invalide ou expire'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
        $entityManager->flush();

        return $this->json(['message' => 'Mot de passe reinitialise']);
    }
}
