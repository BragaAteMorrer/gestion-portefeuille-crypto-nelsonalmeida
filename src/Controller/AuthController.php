<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'email and password required'], 400);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword($hasher->hashPassword($user, $data['password']));

        $em->persist($user);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, UserRepository $repo, UserPasswordHasherInterface $hasher, TokenStorageInterface $tokenStorage, SessionInterface $session): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'email and password required'], 400);
        }

        $user = $repo->findOneBy(['email' => $data['email']]);
        if (!$user) {
            return new JsonResponse(['error' => 'invalid credentials'], 401);
        }

        if (!$hasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'invalid credentials'], 401);
        }

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($token);
        $session->set('_security_main', serialize($token));

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(SessionInterface $session, TokenStorageInterface $tokenStorage): JsonResponse
    {
        $tokenStorage->setToken(null);
        $session->invalidate();

        return new JsonResponse(['ok' => true]);
    }
}
