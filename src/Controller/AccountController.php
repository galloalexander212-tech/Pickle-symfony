<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\EventJoin;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AccountController extends AbstractController
{
    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function admin(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if (!$user->isAdmin()) {
            return $this->redirectToRoute('app_account');
        }

        $users = $entityManager->createQueryBuilder()
            ->select('user.id AS id, user.username AS username, user.email AS email, user.role AS role, user.createdAt AS createdAt, COUNT(booking.id) AS totalBookings')
            ->from(User::class, 'user')
            ->leftJoin('user.bookings', 'booking')
            ->groupBy('user.id')
            ->orderBy('user.createdAt', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $bookings = $entityManager->getRepository(Booking::class)->createQueryBuilder('booking')
            ->addSelect('user')
            ->join('booking.user', 'user')
            ->orderBy('booking.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('account/admin.html.twig', [
            'admin' => $user,
            'users' => $users,
            'bookings' => $bookings,
        ]);
    }

    #[Route('/account', name: 'app_account', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $bookings = $entityManager->getRepository(Booking::class)->findBy(
            ['user' => $user],
            ['bookingDate' => 'DESC', 'bookingTime' => 'DESC'],
        );

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila'));
        $eventJoins = $entityManager->getRepository(EventJoin::class)->createQueryBuilder('eventJoin')
            ->addSelect('event')
            ->join('eventJoin.event', 'event')
            ->where('eventJoin.user = :user')
            ->setParameter('user', $user)
            ->addSelect('CASE WHEN event.eventDate < :today THEN 1 ELSE 0 END AS HIDDEN pastOrder')
            ->setParameter('today', $today)
            ->orderBy('pastOrder', 'ASC')
            ->addOrderBy('event.eventDate', 'ASC')
            ->addOrderBy('event.eventTime', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('account/index.html.twig', [
            'user' => $user,
            'bookings' => $bookings,
            'eventJoins' => $eventJoins,
        ]);
    }

    #[Route('/account/edit', name: 'app_account_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $errors = [];
        $activeForm = '';
        $username = $user->getUsername() ?? '';
        $email = $user->getEmail() ?? '';

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');

            if ($action === 'profile') {
                $activeForm = 'profile';
                $username = trim((string) $request->request->get('username', ''));
                $email = trim((string) $request->request->get('email', ''));

                if ($username === '') {
                    $errors['username'] = 'Username is required.';
                }
                if ($email === '') {
                    $errors['email'] = 'Email is required.';
                }

                if ($errors === []) {
                    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                        $errors['username'] = 'Username must be 3-20 characters (letters, numbers and underscores only).';
                    }
                    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                        $errors['email'] = 'Please enter a valid email address.';
                    }
                }

                if ($errors === []) {
                    $existingUsername = $users->findOneByUsernameOrEmail($username, $user->getId());
                    if ($existingUsername !== null && strcasecmp((string) $existingUsername->getUsername(), $username) === 0) {
                        $errors['username'] = 'That username is already taken.';
                    } else {
                        $existingEmail = $users->findOneByUsernameOrEmail($email, $user->getId());
                        if ($existingEmail !== null) {
                            $errors['email'] = 'That email is already registered.';
                        }
                    }
                }

                if ($errors === []) {
                    $user->setUsername($username);
                    $user->setEmail($email);
                    $entityManager->flush();
                    $this->addFlash('success', 'Profile updated!');

                    return $this->redirectToRoute('app_account_edit');
                }
            } elseif ($action === 'password') {
                $activeForm = 'password';
                $current = (string) $request->request->get('current_password', '');
                $new = (string) $request->request->get('new_password', '');
                $confirm = (string) $request->request->get('confirm_password', '');

                if (trim($current) === '') {
                    $errors['current_password'] = 'Current password is required.';
                }
                if (trim($new) === '') {
                    $errors['new_password'] = 'New password is required.';
                }
                if (trim($confirm) === '') {
                    $errors['confirm_password'] = 'Password confirmation is required.';
                }

                if ($errors === []) {
                    if (strlen($new) < 8) {
                        $errors['new_password'] = 'Password must be at least 8 characters.';
                    }
                    if ($new !== $confirm) {
                        $errors['confirm_password'] = 'Passwords do not match.';
                    }
                }

                if ($errors === [] && !$passwordHasher->isPasswordValid($user, $current)) {
                    $errors['current_password'] = 'Your current password is incorrect.';
                }
                if ($errors === [] && $passwordHasher->isPasswordValid($user, $new)) {
                    $errors['new_password'] = 'New password must be different from the current one.';
                }

                if ($errors === []) {
                    $user->setPasswordHash($passwordHasher->hashPassword($user, $new));
                    $entityManager->flush();
                    $this->addFlash('success', 'Password changed successfully!');

                    return $this->redirectToRoute('app_account_edit');
                }
            }
        }

        return $this->render('account/edit.html.twig', [
            'user' => $user,
            'errors' => $errors,
            'activeForm' => $activeForm,
            'username' => $username,
            'email' => $email,
        ]);
    }
}