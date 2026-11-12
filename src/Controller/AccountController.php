<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\EventJoin;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AccountController extends AbstractController
{
    #[Route('/account/bookings/{id}/claim-payment', name: 'app_account_booking_claim', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function claimBookingPayment(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $booking = $user instanceof User
            ? $entityManager->getRepository(Booking::class)->findOneBy(['id' => $id, 'user' => $user])
            : null;

        if (!$booking instanceof Booking) {
            throw $this->createNotFoundException('Booking not found.');
        }
        if (!$this->isCsrfTokenValid('claim_booking_payment_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'The payment claim expired. Please try again.');

            return $this->redirectToRoute('app_account');
        }
        if ($booking->getStatus() !== 'pending') {
            $this->addFlash('error', 'This booking is no longer waiting for a payment claim.');

            return $this->redirectToRoute('app_account');
        }

        $court = $booking->getCourt();
        if ($court === null || (float) ($court->getPrice() ?? 0) <= 0) {
            $this->addFlash('error', 'This booking does not require a payment claim.');

            return $this->redirectToRoute('app_account');
        }
        if (trim((string) $court->getQrPath()) === '') {
            $this->addFlash('error', 'The owner has not set a payment QR yet.');

            return $this->redirectToRoute('app_account');
        }

        $booking->setStatus('payment_claimed');
        $entityManager->flush();
        $this->addFlash('success', "Payment claimed — waiting for the owner's confirmation");

        return $this->redirectToRoute('app_account');
    }

    #[Route('/account/event-joins/{id}/claim-payment', name: 'app_account_event_join_claim', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function claimEventJoinPayment(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $eventJoin = $user instanceof User
            ? $entityManager->getRepository(EventJoin::class)->findOneBy(['id' => $id, 'user' => $user])
            : null;

        if (!$eventJoin instanceof EventJoin) {
            throw $this->createNotFoundException('Event registration not found.');
        }
        if (!$this->isCsrfTokenValid('claim_event_join_payment_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'The payment claim expired. Please try again.');

            return $this->redirectToRoute('app_account');
        }
        if ($eventJoin->getStatus() !== 'pending') {
            $this->addFlash('error', 'This event registration is no longer waiting for a payment claim.');

            return $this->redirectToRoute('app_account');
        }

        $event = $eventJoin->getEvent();
        $court = $event?->getCourt();
        if ($event === null || (float) ($event->getEntranceFee() ?? 0) <= 0) {
            $this->addFlash('error', 'This event does not require a payment claim.');

            return $this->redirectToRoute('app_account');
        }
        if ($court === null || trim((string) $court->getQrPath()) === '') {
            $this->addFlash('error', 'The owner has not set a payment QR yet.');

            return $this->redirectToRoute('app_account');
        }

        $eventJoin->setStatus('payment_claimed');
        $entityManager->flush();
        $this->addFlash('success', "Payment claimed — waiting for the owner's confirmation");

        return $this->redirectToRoute('app_account');
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

    #[Route('/account/avatar', name: 'app_account_avatar', methods: ['POST'])]
    public function updateAvatar(
        Request $request,
        EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('account_avatar', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'The avatar form expired. Please try again.');

            return $this->redirectToRoute('app_account');
        }

        $upload = $request->files->get('avatar');
        if (!$upload instanceof UploadedFile || !$upload->isValid() || ($upload->getSize() ?? PHP_INT_MAX) > 2 * 1024 * 1024) {
            $this->addFlash('error', 'Choose a valid image no larger than 2 MB.');

            return $this->redirectToRoute('app_account');
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $mimeType = $upload->getMimeType();
        if (!isset($extensions[$mimeType])) {
            $this->addFlash('error', 'Avatar must be a JPG, PNG, or WEBP image.');

            return $this->redirectToRoute('app_account');
        }

        $directory = $projectDir . '/public/uploads/avatars';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $this->addFlash('error', 'The avatar could not be saved. Please try again later.');

            return $this->redirectToRoute('app_account');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
        try {
            $upload->move($directory, $filename);
        } catch (FileException) {
            $this->addFlash('error', 'The avatar could not be saved. Please try again later.');

            return $this->redirectToRoute('app_account');
        }

        $previousAvatar = $user->getAvatar();
        $user->setAvatar($filename);
        $entityManager->flush();

        if ($previousAvatar !== null
            && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $previousAvatar) === 1
        ) {
            $previousPath = $directory . '/' . $previousAvatar;
            if (is_file($previousPath) && !unlink($previousPath)) {
                throw new \RuntimeException('Unable to remove the previous avatar file.');
            }
        }

        $this->addFlash('success', 'Avatar updated!');

        return $this->redirectToRoute('app_account');
    }

    #[Route('/account/color', name: 'app_account_color', methods: ['POST'])]
    public function updateAvatarColor(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $colors = ['#2ecc71', '#ff6b9d', '#3b82f6', '#8e44ad', '#b8a1e3', '#1b4332'];
        $color = $request->request->get('avatar_color', '');
        if (!$this->isCsrfTokenValid('account_color', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Choose a valid avatar color and try again.');

            return $this->redirectToRoute('app_account');
        }
        if (!is_string($color) || !in_array($color, $colors, true)) {
            $this->addFlash('error', 'Choose a valid avatar color and try again.');

            return $this->redirectToRoute('app_account');
        }

        $user->setAvatarColor($color);
        $entityManager->flush();
        $this->addFlash('success', 'Avatar color updated!');

        return $this->redirectToRoute('app_account');
    }

}