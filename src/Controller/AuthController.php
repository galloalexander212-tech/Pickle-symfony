<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute($this->getUser()->isAdmin() ? 'app_admin' : 'app_account');
        }

        $session = $request->getSession();
        $errors = $session->remove('login_form_errors') ?? [];
        $loginIdentifier = $session->remove('login_identifier') ?? '';

        return $this->render('security/login.html.twig', [
            'activeTab' => 'login',
            'errors' => is_array($errors) ? $errors : [],
            'loginIdentifier' => $loginIdentifier,
            'notice' => (string) $request->query->get('notice', ''),
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute($this->getUser()->isAdmin() ? 'app_admin' : 'app_account');
        }

        $errors = [];
        $username = '';
        $email = '';

        if ($request->isMethod('POST')) {
            $username = trim((string) $request->request->get('username', ''));
            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');
            $confirm = (string) $request->request->get('confirm', '');

            if (trim($username) === '') {
                $errors['username'] = 'Username is required.';
            }
            if (trim($email) === '') {
                $errors['email'] = 'Email is required.';
            }
            if (trim($password) === '') {
                $errors['password'] = 'Password is required.';
            }
            if (trim($confirm) === '') {
                $errors['confirm'] = 'Password confirmation is required.';
            }

            if ($errors === []) {
                if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                    $errors['username'] = 'Username must be 3-20 characters (letters, numbers and underscores only).';
                }
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $errors['email'] = 'Please enter a valid email address.';
                }
                if (strlen($password) < 8) {
                    $errors['password'] = 'Password must be at least 8 characters.';
                }
                if ($password !== $confirm) {
                    $errors['confirm'] = 'Passwords do not match.';
                }
            }

            if ($errors === []) {
                $existing = $users->findOneByUsernameOrEmail($username);
                if ($existing !== null && strcasecmp((string) $existing->getUsername(), $username) === 0) {
                    $errors['username'] = 'That username is already taken.';
                } elseif ($users->findOneByUsernameOrEmail($email) !== null) {
                    $errors['email'] = 'That email is already registered.';
                }
            }

            if ($errors === []) {
                $user = new User();
                $user->setUsername($username);
                $user->setEmail($email);
                $user->setPasswordHash($passwordHasher->hashPassword($user, $password));
                $user->setRole('user');
                $user->setFailedAttempts(0);
                $user->setLockedUntil(null);

                try {
                    $entityManager->persist($user);
                    $entityManager->flush();
                } catch (Exception) {
                    $errors['username'] = 'Something went wrong. Please try again.';
                }

                if ($errors === []) {
                    $this->addFlash(
                        'success',
                        'Your PICKLE account has been created. Log in to book courts, join open plays and compete.',
                    );

                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'activeTab' => 'register',
            'errors' => $errors,
            'username' => $username,
            'email' => $email,
            'notice' => (string) $request->query->get('notice', ''),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Logout is handled by the security firewall.');
    }
}