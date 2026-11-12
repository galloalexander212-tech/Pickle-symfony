<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\Event;
use App\Entity\EventJoin;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    #[Route('/admin', name: 'app_admin', methods: ['GET', 'POST'])]
    public function dashboard(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');
            $bookingId = $request->request->getInt('booking_id');

            if ($bookingId > 0) {
                if (!$this->isCsrfTokenValid('admin_booking_' . $bookingId, (string) $request->request->get('_token', ''))) {
                    $this->addFlash('error', 'The booking action expired. Please try again.');

                    return $this->redirectToRoute('app_admin');
                }

                $booking = $entityManager->getRepository(Booking::class)->find($bookingId);
                if (!$booking instanceof Booking) {
                    $this->addFlash('error', 'Booking not found.');
                } elseif ($action === 'confirm') {
                    $booking->setStatus('confirmed');
                    $entityManager->flush();
                    $this->addFlash('success', 'Booking confirmed.');
                } elseif ($action === 'bcancel') {
                    $booking->setStatus('cancelled');
                    $entityManager->flush();
                    $this->addFlash('success', 'Booking cancelled.');
                } elseif ($action === 'bdelete') {
                    $entityManager->remove($booking);
                    $entityManager->flush();
                    $this->addFlash('success', 'Booking deleted.');
                } else {
                    $this->addFlash('error', 'Invalid booking action.');
                }
            } else {
                $this->addFlash('error', 'Invalid booking.');
            }

            return $this->redirectToRoute('app_admin');
        }

        $bookings = $entityManager->getRepository(Booking::class)->createQueryBuilder('booking')
            ->addSelect('user')
            ->join('booking.user', 'user')
            ->orderBy('booking.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('account/admin.html.twig', [
            'stats' => [
                'users' => $entityManager->getRepository(User::class)->count([]),
                'staff' => $entityManager->getRepository(User::class)->createQueryBuilder('user')
                    ->select('COUNT(user.id)')
                    ->where('user.role = :role')
                    ->setParameter('role', 'staff')
                    ->getQuery()
                    ->getSingleScalarResult(),
                'admins' => $entityManager->getRepository(User::class)->createQueryBuilder('user')
                    ->select('COUNT(user.id)')
                    ->where('user.role = :role')
                    ->setParameter('role', 'admin')
                    ->getQuery()
                    ->getSingleScalarResult(),
                'courts' => $entityManager->getRepository(Court::class)->count([]),
                'events' => $entityManager->getRepository(Event::class)->count([]),
                'bookings' => $entityManager->getRepository(Booking::class)->count([]),
                'pendingBookings' => $entityManager->getRepository(Booking::class)->count(['status' => 'payment_claimed']),
                'pendingJoins' => $entityManager->getRepository(EventJoin::class)->count(['status' => 'payment_claimed']),
            ],
            'bookings' => $bookings,
        ]);
    }

    #[Route('/admin/users', name: 'app_admin_users', methods: ['GET', 'POST'])]
    public function users(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        /** @var User $admin */
        $admin = $this->getUser();

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');
            $userId = $request->request->getInt('user_id');
            $target = $userId > 0 ? $entityManager->getRepository(User::class)->find($userId) : null;
            $tokenId = match ($action) {
                'create' => 'admin_create_user',
                'update' => 'admin_update_user_' . $userId,
                'reset_password' => 'admin_reset_password_' . $userId,
                'delete' => 'admin_delete_user_' . $userId,
                'toggle_status' => 'admin_toggle_user_status_' . $userId,
                default => '',
            };

            if ($tokenId === '' || !$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token', ''))) {
                $this->addFlash('error', 'The account form expired or the action is invalid. Please try again.');

                return $this->redirectToRoute('app_admin_users');
            }

            if ($action === 'create' || $action === 'update') {
                $isNew = $action === 'create';
                $user = $isNew ? new User() : $target;
                if (!$user instanceof User) {
                    $this->addFlash('error', 'Account not found.');

                    return $this->redirectToRoute('app_admin_users');
                }

                $username = trim((string) $request->request->get('username', ''));
                $email = trim((string) $request->request->get('email', ''));
                $role = (string) $request->request->get('role', '');
                $password = (string) $request->request->get('password', '');
                $roleValues = ['ROLE_USER' => 'user', 'ROLE_STAFF' => 'staff', 'ROLE_ADMIN' => 'admin'];

                if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                    $this->addFlash('error', 'Username must be 3-20 characters (letters, numbers and underscores only).');
                } elseif (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $this->addFlash('error', 'Please enter a valid email address.');
                } elseif (!isset($roleValues[$role])) {
                    $this->addFlash('error', 'Pick a valid account role.');
                } elseif (($duplicate = $users->findOneByUsernameOrEmail($username, $user->getId())) !== null) {
                    $this->addFlash(
                        'error',
                        strcasecmp((string) $duplicate->getUsername(), $username) === 0
                            ? 'That username is already taken.'
                            : 'That email is already registered.',
                    );
                } elseif ($users->findOneByUsernameOrEmail($email, $user->getId()) !== null) {
                    $this->addFlash('error', 'That email is already registered.');
                } elseif ($isNew && ($password === '' || strlen($password) < 8 || strlen($password) > 100)) {
                    $this->addFlash('error', 'Password must be between 8 and 100 characters.');
                } else {
                    $user->setUsername($username);
                    $user->setEmail($email);
                    $user->setRole($roleValues[$role]);
                    if ($isNew) {
                        $user->setPasswordHash($passwordHasher->hashPassword($user, $password));
                        $entityManager->persist($user);
                    }
                    $entityManager->flush();
                    $this->addFlash('success', $isNew ? 'Account created.' : 'Account updated.');
                }
            } elseif ($action === 'reset_password') {
                $password = (string) $request->request->get('password', '');
                if (!$target instanceof User) {
                    $this->addFlash('error', 'Account not found.');
                } elseif ($password === '' || strlen($password) < 8 || strlen($password) > 100) {
                    $this->addFlash('error', 'Temporary password must be between 8 and 100 characters.');
                } else {
                    $target->setPasswordHash($passwordHasher->hashPassword($target, $password));
                    $entityManager->flush();
                    $this->addFlash('success', 'Password reset successfully.');
                }
            } elseif ($action === 'toggle_status') {
                if (!$target instanceof User) {
                    $this->addFlash('error', 'Account not found.');
                } elseif ($target->getId() === $admin->getId()) {
                    $this->addFlash('error', 'You cannot disable your own account.');
                } else {
                    $target->setAccountStatus($target->getAccountStatus() === 'active' ? 'disabled' : 'active');
                    $entityManager->flush();
                    $this->addFlash('success', $target->getAccountStatus() === 'active' ? 'Account enabled.' : 'Account disabled.');
                }
            } elseif ($action === 'delete') {
                if (!$target instanceof User) {
                    $this->addFlash('error', 'Account not found.');
                } elseif ($target->getId() === $admin->getId()) {
                    $this->addFlash('error', 'You cannot delete your own account.');
                } else {
                    $entityManager->remove($target);
                    $entityManager->flush();
                    $this->addFlash('success', 'Account deleted.');
                }
            }

            return $this->redirectToRoute('app_admin_users');
        }

        $editId = $request->query->getInt('edit');
        $editUser = $editId > 0 ? $entityManager->getRepository(User::class)->find($editId) : null;

        return $this->render('account/users.html.twig', [
            'admin' => $admin,
            'users' => $entityManager->getRepository(User::class)->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']),
            'editUser' => $editUser,
        ]);
    }

    #[Route('/admin/courts', name: 'app_admin_courts', methods: ['GET', 'POST'])]
    public function courts(Request $request, EntityManagerInterface $entityManager): Response
    {
        $errors = [];
        $courtId = $request->query->getInt('edit');
        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');

            if ($action === 'create' || $action === 'update') {
                $courtId = $action === 'update' ? $request->request->getInt('court_id') : 0;
                $court = $courtId > 0 ? $entityManager->getRepository(Court::class)->find($courtId) : new Court();
                $name = trim((string) $request->request->get('name', ''));
                $location = trim((string) $request->request->get('location', ''));
                $rating = (float) $request->request->get('rating', 0);

                if ($name === '') {
                    $errors['name'] = 'Court name is required.';
                }
                if ($location === '') {
                    $errors['location'] = 'Location is required.';
                }
                if ($rating < 0 || $rating > 5) {
                    $errors['rating'] = 'Rating must be between 0 and 5.';
                }

                if ($errors === []) {
                    $duplicate = $entityManager->getRepository(Court::class)->createQueryBuilder('court')
                        ->select('court.id')
                        ->where('court.name = :name')
                        ->setParameter('name', $name)
                        ->setMaxResults(1);

                    if ($action === 'update') {
                        $duplicate->andWhere('court.id != :id')->setParameter('id', $courtId);
                    }

                    if ($duplicate->getQuery()->getOneOrNullResult() !== null) {
                        $errors['name'] = 'A court with that name already exists.';
                    }
                }

                $imagePath = null;
                $upload = $request->files->get('image');
                if ($errors === [] && $upload instanceof UploadedFile && $upload->getClientOriginalName() !== '') {
                    $imagePath = $this->storeImage($upload, 'courts');
                    if ($imagePath === false) {
                        $errors['image'] = 'Photo upload failed — use JPG, PNG, WEBP or GIF under 5MB.';
                    }
                }

                if ($errors === [] && $court instanceof Court) {
                    $oldImage = $court->getImage();
                    $court->setName($name);
                    $court->setLocation($location);
                    $court->setRating(number_format($rating, 1, '.', ''));
                    if (is_string($imagePath)) {
                        $court->setImage($imagePath);
                    }

                    $entityManager->persist($court);
                    $entityManager->flush();

                    if (is_string($imagePath) && $oldImage !== null) {
                        $this->removeStoredImage($oldImage, 'courts');
                    }

                    $this->addFlash('success', $action === 'create' ? 'Court added!' : 'Court updated!');

                    return $this->redirectToRoute('app_admin_courts');
                }
            } elseif ($action === 'delete') {
                $courtId = $request->request->getInt('court_id');
                if ($courtId > 0) {
                    $court = $entityManager->getRepository(Court::class)->find($courtId);
                    if ($court instanceof Court) {
                        $imagePath = $court->getImage();
                        $entityManager->remove($court);
                        $entityManager->flush();
                        if ($imagePath !== null) {
                            $this->removeStoredImage($imagePath, 'courts');
                        }
                        $this->addFlash('success', 'Court deleted.');
                    }
                }

                return $this->redirectToRoute('app_admin_courts');
            }
        }

        $editCourt = $courtId > 0 ? $entityManager->getRepository(Court::class)->find($courtId) : null;
        $values = $request->isMethod('POST') ? $request->request->all() : [];

        return $this->render('account/courts.html.twig', [
            'courts' => $entityManager->getRepository(Court::class)->findBy([], ['createdAt' => 'DESC']),
            'editCourt' => $editCourt,
            'errors' => $errors,
            'values' => $values,
        ]);
    }

    #[Route('/admin/events', name: 'app_admin_events', methods: ['GET', 'POST'])]
    public function events(Request $request, EntityManagerInterface $entityManager): Response
    {
        $errors = [];
        $eventId = $request->query->getInt('edit');
        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');

            if ($action === 'create' || $action === 'update') {
                $eventId = $action === 'update' ? $request->request->getInt('event_id') : 0;
                $event = $eventId > 0 ? $entityManager->getRepository(Event::class)->find($eventId) : new Event();
                $type = (string) $request->request->get('type', '');
                $title = trim((string) $request->request->get('title', ''));
                $description = trim((string) $request->request->get('description', ''));
                $location = trim((string) $request->request->get('location', ''));
                $date = trim((string) $request->request->get('date', ''));
                $time = trim((string) $request->request->get('time', ''));
                $maxPlayers = (int) $request->request->get('max_players', 0);
                $prize = trim((string) $request->request->get('prize', ''));

                if ($title === '') {
                    $errors['title'] = 'Title is required.';
                }
                if ($location === '') {
                    $errors['location'] = 'Location is required.';
                }
                if ($date === '') {
                    $errors['date'] = 'Date is required.';
                }
                if ($time === '') {
                    $errors['time'] = 'Time is required.';
                }
                if (!in_array($type, ['open_play', 'tournament'], true)) {
                    $errors['type'] = 'Pick a valid event type.';
                }
                if ($maxPlayers < 2 || $maxPlayers > 64) {
                    $errors['max_players'] = 'Max players must be between 2 and 64.';
                }
                if ($action === 'create' && $date !== '' && $date < date('Y-m-d')) {
                    $errors['date'] = "Date can't be in the past.";
                }

                $imagePath = null;
                $upload = $request->files->get('image');
                if ($errors === [] && $upload instanceof UploadedFile && $upload->getClientOriginalName() !== '') {
                    $imagePath = $this->storeImage($upload, 'events');
                    if ($imagePath === false) {
                        $errors['image'] = 'Photo upload failed — use JPG, PNG, WEBP or GIF under 5MB.';
                    }
                }

                if ($errors === [] && $event instanceof Event) {
                    $eventDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                    $eventTime = \DateTimeImmutable::createFromFormat('!H:i', $time);
                    if ($eventDate === false || $eventDate->format('Y-m-d') !== $date) {
                        $errors['date'] = "Date can't be in the past.";
                    } elseif ($eventTime === false) {
                        $errors['time'] = 'Time is required.';
                    } else {
                        $oldImage = $event->getImage();
                        $event->setType($type);
                        $event->setTitle($title);
                        $event->setDescription($description);
                        $event->setLocation($location);
                        $event->setEventDate($eventDate);
                        $event->setEventTime($eventTime);
                        $event->setMaxPlayers($maxPlayers);
                        $event->setPrize($prize);
                        if (is_string($imagePath)) {
                            $event->setImage($imagePath);
                        }
                        if ($action === 'create') {
                            $event->setCreator(null);
                        }

                        $entityManager->persist($event);
                        $entityManager->flush();

                        if (is_string($imagePath) && $oldImage !== null) {
                            $this->removeStoredImage($oldImage, 'events');
                        }

                        $this->addFlash('success', ucfirst(str_replace('_', ' ', $type)) . ($action === 'create' ? ' created!' : ' updated!'));

                        return $this->redirectToRoute('app_admin_events');
                    }
                }
            } elseif ($action === 'delete') {
                $eventId = $request->request->getInt('event_id');
                if ($eventId > 0) {
                    $event = $entityManager->getRepository(Event::class)->find($eventId);
                    if ($event instanceof Event) {
                        $imagePath = $event->getImage();
                        $entityManager->remove($event);
                        $entityManager->flush();
                        if ($imagePath !== null) {
                            $this->removeStoredImage($imagePath, 'events');
                        }
                        $this->addFlash('success', 'Event deleted.');
                    }
                }

                return $this->redirectToRoute('app_admin_events');
            }
        }

        $editEvent = $eventId > 0 ? $entityManager->getRepository(Event::class)->find($eventId) : null;
        $values = $request->isMethod('POST') ? $request->request->all() : [];
        $events = $entityManager->getRepository(Event::class)->findBy([], ['eventDate' => 'ASC', 'eventTime' => 'ASC']);

        return $this->render('account/events.html.twig', [
            'events' => $events,
            'editEvent' => $editEvent,
            'errors' => $errors,
            'values' => $values,
        ]);
    }

    private function storeImage(UploadedFile $upload, string $folder): string|false|null
    {
        if ($upload->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (!$upload->isValid() || $upload->getSize() > 5 * 1024 * 1024) {
            return false;
        }

        $image = @getimagesize($upload->getPathname());
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if ($image === false || !isset($extensions[$image['mime']])) {
            return false;
        }

        $prefix = $folder === 'courts' ? 'courts' : 'events';
        $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$image['mime']];
        $directory = $this->projectDir . '/public/uploads/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }

        try {
            $upload->move($directory, $filename);
        } catch (\Throwable) {
            return false;
        }

        return 'uploads/' . $folder . '/' . $filename;
    }

    private function removeStoredImage(string $imagePath, string $folder): void
    {
        $prefix = 'uploads/' . $folder . '/';
        if (!str_starts_with($imagePath, $prefix)) {
            return;
        }

        $path = $this->projectDir . '/public/' . $imagePath;
        if (is_file($path)) {
            unlink($path);
        }
    }
}