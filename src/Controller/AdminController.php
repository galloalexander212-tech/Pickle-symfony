<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AdminController extends AbstractController
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    #[Route('/admin', name: 'app_admin', methods: ['GET', 'POST'])]
    public function dashboard(Request $request, EntityManagerInterface $entityManager): Response
    {
        $admin = $this->getAdminOrRedirect();
        if ($admin instanceof Response) {
            return $admin;
        }

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');
            $userId = $request->request->getInt('user_id');
            $bookingId = $request->request->getInt('booking_id');

            if ($userId > 0 && $userId !== $admin->getId()) {
                $user = $entityManager->getRepository(User::class)->find($userId);

                if ($action === 'toggle' && $user instanceof User) {
                    $user->setRole($user->getRole() === 'admin' ? 'user' : 'admin');
                    $entityManager->flush();
                }

                if ($action === 'delete' && $user instanceof User && $user->getRole() === 'user') {
                    $entityManager->remove($user);
                    $entityManager->flush();
                }
            }

            if ($bookingId > 0) {
                $booking = $entityManager->getRepository(Booking::class)->find($bookingId);

                if ($booking instanceof Booking) {
                    if ($action === 'confirm') {
                        $booking->setStatus('confirmed');
                        $entityManager->flush();
                    } elseif ($action === 'bcancel') {
                        $booking->setStatus('cancelled');
                        $entityManager->flush();
                    } elseif ($action === 'bdelete') {
                        $entityManager->remove($booking);
                        $entityManager->flush();
                    }
                }
            }

            return $this->redirectToRoute('app_admin');
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
            'admin' => $admin,
            'users' => $users,
            'bookings' => $bookings,
        ]);
    }

    #[Route('/admin/courts', name: 'app_admin_courts', methods: ['GET', 'POST'])]
    public function courts(Request $request, EntityManagerInterface $entityManager): Response
    {
        $admin = $this->getAdminOrRedirect();
        if ($admin instanceof Response) {
            return $admin;
        }

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
        $admin = $this->getAdminOrRedirect();
        if ($admin instanceof Response) {
            return $admin;
        }

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

    private function getAdminOrRedirect(): User|Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        if (!$user->isAdmin()) {
            return $this->redirectToRoute('app_account');
        }

        return $user;
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