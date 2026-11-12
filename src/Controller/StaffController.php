<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\Event;
use App\Entity\EventJoin;
use App\Entity\User;
use App\Service\CourtEventImageStorage;
use App\Service\QrImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
class StaffController extends AbstractController
{
    #[Route('/staff', name: 'app_staff', methods: ['GET'])]
    public function home(EntityManagerInterface $entityManager): Response
    {
        $staff = $this->staffUser();

        return $this->render('staff/home.html.twig', [
            'stats' => [
                'courts' => $entityManager->getRepository(Court::class)->count(['owner' => $staff]),
                'events' => $this->ownedEventQuery($entityManager, $staff)->select('COUNT(event.id)')->getQuery()->getSingleScalarResult(),
                'bookings' => $this->ownedBookingQuery($entityManager, $staff)
                    ->select('COUNT(booking.id)')
                    ->andWhere('booking.status = :claimed')
                    ->setParameter('claimed', 'payment_claimed')
                    ->getQuery()
                    ->getSingleScalarResult(),
                'joins' => $this->ownedJoinQuery($entityManager, $staff)
                    ->select('COUNT(eventJoin.id)')
                    ->andWhere('eventJoin.status = :claimed')
                    ->setParameter('claimed', 'payment_claimed')
                    ->getQuery()
                    ->getSingleScalarResult(),
            ],
        ]);
    }

    #[Route('/staff/courts', name: 'app_staff_courts', methods: ['GET'])]
    public function courts(EntityManagerInterface $entityManager): Response
    {
        return $this->render('staff/courts.html.twig', [
            'courts' => $entityManager->getRepository(Court::class)->findBy(
                ['owner' => $this->staffUser()],
                ['createdAt' => 'DESC'],
            ),
        ]);
    }

    #[Route('/staff/courts/new', name: 'app_staff_court_new', methods: ['GET', 'POST'])]
    public function newCourt(
        Request $request,
        EntityManagerInterface $entityManager,
        QrImageStorage $qrImageStorage,
        CourtEventImageStorage $imageStorage,
    ): Response {
        $staff = $this->staffUser();

        return $this->courtForm($request, $entityManager, $qrImageStorage, $imageStorage, new Court(), $staff);
    }

    #[Route('/staff/courts/{id}/edit', name: 'app_staff_court_edit', methods: ['GET', 'POST'])]
    public function editCourt(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        QrImageStorage $qrImageStorage,
        CourtEventImageStorage $imageStorage,
    ): Response {
        $staff = $this->staffUser();
        $court = $entityManager->getRepository(Court::class)->find($id);
        if (!$court instanceof Court) {
            throw $this->createNotFoundException('Court not found.');
        }
        $this->assertCourtOwnership($court, $staff);

        return $this->courtForm($request, $entityManager, $qrImageStorage, $imageStorage, $court, $staff);
    }

    #[Route('/staff/courts/{id}/delete', name: 'app_staff_court_delete', methods: ['POST'])]
    public function deleteCourt(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        QrImageStorage $qrImageStorage,
        CourtEventImageStorage $imageStorage,
    ): Response {
        $staff = $this->staffUser();
        $court = $entityManager->getRepository(Court::class)->find($id);
        if (!$court instanceof Court) {
            throw $this->createNotFoundException('Court not found.');
        }
        $this->assertCourtOwnership($court, $staff);

        if (!$this->isCsrfTokenValid('staff_delete_court_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'The delete confirmation expired. Please try again.');

            return $this->redirectToRoute('app_staff_courts');
        }

        $qrPath = $court->getQrPath();
        $imagePath = $court->getImage();
        $entityManager->remove($court);
        $entityManager->flush();
        $qrImageStorage->remove($qrPath);
        $imageStorage->remove($imagePath, 'courts');
        $this->addFlash('success', 'Court deleted.');

        return $this->redirectToRoute('app_staff_courts');
    }

    #[Route('/staff/events', name: 'app_staff_events', methods: ['GET'])]
    public function events(EntityManagerInterface $entityManager): Response
    {
        $staff = $this->staffUser();

        return $this->render('staff/events.html.twig', [
            'events' => $this->ownedEventQuery($entityManager, $staff)
                ->orderBy('event.eventDate', 'ASC')
                ->addOrderBy('event.eventTime', 'ASC')
                ->getQuery()
                ->getResult(),
        ]);
    }

    #[Route('/staff/events/new', name: 'app_staff_event_new', methods: ['GET', 'POST'])]
    public function newEvent(
        Request $request,
        EntityManagerInterface $entityManager,
        CourtEventImageStorage $imageStorage,
    ): Response {
        return $this->eventForm($request, $entityManager, $imageStorage, new Event(), $this->staffUser());
    }

    #[Route('/staff/events/{id}/edit', name: 'app_staff_event_edit', methods: ['GET', 'POST'])]
    public function editEvent(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CourtEventImageStorage $imageStorage,
    ): Response {
        $staff = $this->staffUser();
        $event = $entityManager->getRepository(Event::class)->find($id);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Event not found.');
        }
        $this->assertEventOwnership($event, $staff);

        return $this->eventForm($request, $entityManager, $imageStorage, $event, $staff);
    }

    #[Route('/staff/events/{id}/delete', name: 'app_staff_event_delete', methods: ['POST'])]
    public function deleteEvent(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CourtEventImageStorage $imageStorage,
    ): Response {
        $staff = $this->staffUser();
        $event = $entityManager->getRepository(Event::class)->find($id);
        if (!$event instanceof Event) {
            throw $this->createNotFoundException('Event not found.');
        }
        $this->assertEventOwnership($event, $staff);

        if (!$this->isCsrfTokenValid('staff_delete_event_' . $id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'The delete confirmation expired. Please try again.');

            return $this->redirectToRoute('app_staff_events');
        }

        $imagePath = $event->getImage();
        $entityManager->remove($event);
        $entityManager->flush();
        $imageStorage->remove($imagePath, 'events');
        $this->addFlash('success', 'Event deleted.');

        return $this->redirectToRoute('app_staff_events');
    }

    #[Route('/staff/bookings', name: 'app_staff_bookings', methods: ['GET'])]
    public function bookings(EntityManagerInterface $entityManager): Response
    {
        $staff = $this->staffUser();

        return $this->render('staff/bookings.html.twig', [
            'bookings' => $this->ownedBookingQuery($entityManager, $staff)
                ->orderBy('booking.bookingDate', 'DESC')
                ->addOrderBy('booking.bookingTime', 'DESC')
                ->getQuery()
                ->getResult(),
            'joins' => $this->ownedJoinQuery($entityManager, $staff)
                ->orderBy('event.eventDate', 'DESC')
                ->addOrderBy('eventJoin.joinedAt', 'DESC')
                ->getQuery()
                ->getResult(),
        ]);
    }

    #[Route('/staff/bookings/{type}/{id}/status', name: 'app_staff_booking_status', requirements: ['type' => 'booking|join'], methods: ['POST'])]
    public function updateBookingStatus(
        string $type,
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $staff = $this->staffUser();
        $action = (string) $request->request->get('action', '');

        if ($type === 'booking') {
            $record = $entityManager->getRepository(Booking::class)->find($id);
            if (!$record instanceof Booking) {
                throw $this->createNotFoundException('Booking not found.');
            }
            $court = $record->getCourt();
            if ($court === null || $court->getOwner() !== $staff) {
                throw new AccessDeniedException('You do not own this court.');
            }
            $status = $record->getStatus();
            $isFree = (float) ($court->getPrice() ?? 0) <= 0;
        } else {
            $record = $entityManager->getRepository(EventJoin::class)->find($id);
            if (!$record instanceof EventJoin) {
                throw $this->createNotFoundException('Event registration not found.');
            }
            $event = $record->getEvent();
            $court = $event?->getCourt();
            if ($court === null || $court->getOwner() !== $staff) {
                throw new AccessDeniedException('You do not own this court.');
            }
            $status = $record->getStatus();
            $isFree = (float) ($event?->getEntranceFee() ?? 0) <= 0;
        }

        if (!in_array($action, ['accept', 'reject'], true)
            || !$this->isCsrfTokenValid('staff_payment_' . $type . '_' . $id . '_' . $action, (string) $request->request->get('_token', ''))
        ) {
            $this->addFlash('error', 'The payment decision expired or the action is invalid. Please try again.');

            return $this->redirectToRoute('app_staff_bookings');
        }

        $canAcceptFreePending = $action === 'accept' && $status === 'pending' && $isFree;
        $canDecideClaimed = $status === 'payment_claimed';
        if (!$canAcceptFreePending && !$canDecideClaimed) {
            $this->addFlash('error', 'This payment record is no longer actionable.');

            return $this->redirectToRoute('app_staff_bookings');
        }
        if ($action === 'reject' && !$canDecideClaimed) {
            $this->addFlash('error', 'Only a claimed payment can be rejected.');

            return $this->redirectToRoute('app_staff_bookings');
        }

        $record->setStatus($action === 'accept' ? 'confirmed' : 'rejected');
        $entityManager->flush();
        $this->addFlash('success', $action === 'accept' ? 'Payment accepted — player confirmed.' : 'Payment rejected.');

        return $this->redirectToRoute('app_staff_bookings');
    }

    private function courtForm(
        Request $request,
        EntityManagerInterface $entityManager,
        QrImageStorage $qrImageStorage,
        CourtEventImageStorage $imageStorage,
        Court $court,
        User $staff,
    ): Response {
        $isEdit = $court->getId() !== null;
        $action = $isEdit ? 'update' : 'create';
        $errors = [];
        $values = $request->isMethod('POST') ? $request->request->all() : [];

        if ($request->isMethod('POST')) {
            $csrfId = $isEdit ? 'staff_update_court_' . $court->getId() : 'staff_create_court';
            if ($request->request->get('action') !== $action
                || !$this->isCsrfTokenValid($csrfId, (string) $request->request->get('_token', ''))
            ) {
                $this->addFlash('error', 'The court form expired or the action is invalid. Please try again.');

                return $this->redirectToRoute($isEdit ? 'app_staff_court_edit' : 'app_staff_courts', $isEdit ? ['id' => $court->getId()] : []);
            }

            $name = trim((string) $request->request->get('name', ''));
            $location = trim((string) $request->request->get('location', ''));
            $rating = trim((string) $request->request->get('rating', ''));
            $price = trim((string) $request->request->get('price', ''));

            if ($name === '' || strlen($name) > 100) {
                $errors['name'] = 'Court name is required and must be 100 characters or fewer.';
            }
            if ($location === '' || strlen($location) > 150) {
                $errors['location'] = 'Location is required and must be 150 characters or fewer.';
            }
            if (!is_numeric($rating) || (float) $rating < 0 || (float) $rating > 5) {
                $errors['rating'] = 'Rating must be between 0 and 5.';
            }
            if ($price !== '' && !$this->isValidMoney($price)) {
                $errors['price'] = 'Enter a price with up to 8 whole-number digits and 2 decimal places.';
            }

            $newImagePath = null;
            $imageUpload = $request->files->get('image');
            if ($errors === [] && $imageUpload instanceof UploadedFile && $imageUpload->getError() !== UPLOAD_ERR_NO_FILE) {
                $newImagePath = $imageStorage->store($imageUpload, 'courts');
                if (!is_string($newImagePath)) {
                    $errors['image'] = 'Photo upload failed. Use JPG, PNG or WEBP up to 2 MB.';
                }
            }

            $newQrPath = null;
            $qrUpload = $request->files->get('qr');
            if ($errors === [] && $qrUpload instanceof UploadedFile && $qrUpload->getError() !== UPLOAD_ERR_NO_FILE) {
                $newQrPath = $qrImageStorage->store($qrUpload);
                if (!is_string($newQrPath)) {
                    $errors['qr'] = 'QR upload failed. Use PNG, JPG or WEBP up to 2 MB.';
                }
            }

            if ($errors !== [] && is_string($newImagePath)) {
                $imageStorage->remove($newImagePath, 'courts');
            }

            if ($errors === []) {
                $oldQrPath = $court->getQrPath();
                $oldImagePath = $court->getImage();
                $court->setName($name)
                    ->setLocation($location)
                    ->setRating(number_format((float) $rating, 1, '.', ''))
                    ->setPrice($price === '' ? null : $price)
                    ->setOwner($staff);
                if (is_string($newQrPath)) {
                    $court->setQrPath($newQrPath);
                }
                if (is_string($newImagePath)) {
                    $court->setImage($newImagePath);
                }

                $entityManager->persist($court);
                $entityManager->flush();

                if (is_string($newQrPath)) {
                    $qrImageStorage->remove($oldQrPath);
                }
                if (is_string($newImagePath)) {
                    $imageStorage->remove($oldImagePath, 'courts');
                }

                $this->addFlash('success', $isEdit ? 'Court updated.' : 'Court created.');

                return $this->redirectToRoute('app_staff_courts');
            }

            $this->addFlash('error', 'Please correct the highlighted court fields.');
        }

        return $this->render('staff/court_form.html.twig', [
            'court' => $court,
            'isEdit' => $isEdit,
            'errors' => $errors,
            'values' => $values,
        ]);
    }

    private function eventForm(
        Request $request,
        EntityManagerInterface $entityManager,
        CourtEventImageStorage $imageStorage,
        Event $event,
        User $staff,
    ): Response {
        $isEdit = $event->getId() !== null;
        $action = $isEdit ? 'update' : 'create';
        $errors = [];
        $values = $request->isMethod('POST') ? $request->request->all() : [];
        $ownedCourts = $entityManager->getRepository(Court::class)->findBy(['owner' => $staff], ['name' => 'ASC']);

        if ($request->isMethod('POST')) {
            $csrfId = $isEdit ? 'staff_update_event_' . $event->getId() : 'staff_create_event';
            if ($request->request->get('action') !== $action
                || !$this->isCsrfTokenValid($csrfId, (string) $request->request->get('_token', ''))
            ) {
                $this->addFlash('error', 'The event form expired or the action is invalid. Please try again.');

                return $this->redirectToRoute($isEdit ? 'app_staff_event_edit' : 'app_staff_events', $isEdit ? ['id' => $event->getId()] : []);
            }

            $type = (string) $request->request->get('type', '');
            $title = trim((string) $request->request->get('title', ''));
            $description = trim((string) $request->request->get('description', ''));
            $date = trim((string) $request->request->get('date', ''));
            $time = trim((string) $request->request->get('time', ''));
            $maxPlayers = filter_var($request->request->get('max_players'), FILTER_VALIDATE_INT);
            $entranceFee = trim((string) $request->request->get('entrance_fee', ''));
            $courtId = $request->request->getInt('court_id');
            $court = $courtId > 0 ? $entityManager->getRepository(Court::class)->find($courtId) : null;

            if ($title === '' || strlen($title) > 150) {
                $errors['title'] = 'Title is required and must be 150 characters or fewer.';
            }
            if (!in_array($type, ['open_play', 'tournament'], true)) {
                $errors['type'] = 'Choose open play or tournament.';
            }
            if ($date === '' || ($eventDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date)) === false
                || $eventDate->format('Y-m-d') !== $date
                || (!$isEdit && $eventDate < new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila')))
            ) {
                $errors['date'] = 'Choose a valid date that is not in the past.';
                $eventDate = null;
            }
            if ($time === '' || ($eventTime = \DateTimeImmutable::createFromFormat('!H:i', $time)) === false
                || $eventTime->format('H:i') !== $time
            ) {
                $errors['time'] = 'Choose a valid event time.';
                $eventTime = null;
            }
            if (!is_int($maxPlayers) || $maxPlayers < 2 || $maxPlayers > 64) {
                $errors['max_players'] = 'Max players must be between 2 and 64.';
            }
            if ($entranceFee !== '' && !$this->isValidMoney($entranceFee)) {
                $errors['entrance_fee'] = 'Enter a fee with up to 8 whole-number digits and 2 decimal places.';
            }
            if ($courtId < 1 || !$court instanceof Court) {
                $errors['court_id'] = 'Choose one of your courts.';
            } else {
                $this->assertCourtOwnership($court, $staff);
            }
            if (strlen($description) > 65535) {
                $errors['description'] = 'Description is too long.';
            }

            $newImagePath = null;
            $imageUpload = $request->files->get('image');
            if ($errors === [] && $imageUpload instanceof UploadedFile && $imageUpload->getError() !== UPLOAD_ERR_NO_FILE) {
                $newImagePath = $imageStorage->store($imageUpload, 'events');
                if (!is_string($newImagePath)) {
                    $errors['image'] = 'Photo upload failed. Use JPG, PNG or WEBP up to 2 MB.';
                }
            }

            if ($errors === []) {
                $oldImagePath = $event->getImage();
                $event->setType($type)
                    ->setTitle($title)
                    ->setDescription($description === '' ? null : $description)
                    ->setLocation((string) $court->getLocation())
                    ->setEventDate($eventDate)
                    ->setEventTime($eventTime)
                    ->setMaxPlayers($maxPlayers)
                    ->setEntranceFee($entranceFee === '' ? null : $entranceFee)
                    ->setCourt($court);
                if (is_string($newImagePath)) {
                    $event->setImage($newImagePath);
                }
                if (!$isEdit) {
                    $event->setCreator($staff);
                }

                $entityManager->persist($event);
                $entityManager->flush();
                if (is_string($newImagePath)) {
                    $imageStorage->remove($oldImagePath, 'events');
                }
                $this->addFlash('success', $isEdit ? 'Event updated.' : 'Event created.');

                return $this->redirectToRoute('app_staff_events');
            }

            $this->addFlash('error', 'Please correct the highlighted event fields.');
        }

        return $this->render('staff/event_form.html.twig', [
            'event' => $event,
            'isEdit' => $isEdit,
            'courts' => $ownedCourts,
            'errors' => $errors,
            'values' => $values,
        ]);
    }

    private function staffUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('A staff account is required.');
        }

        return $user;
    }

    private function assertCourtOwnership(Court $court, User $staff): void
    {
        $owner = $court->getOwner();
        if ($owner === null || $owner->getId() !== $staff->getId()) {
            throw new AccessDeniedException('You do not own this court.');
        }
    }

    private function assertEventOwnership(Event $event, User $staff): void
    {
        $court = $event->getCourt();
        if ($court === null) {
            throw new AccessDeniedException('This event is not attached to an owned court.');
        }
        $this->assertCourtOwnership($court, $staff);
    }

    private function isValidMoney(string $amount): bool
    {
        return preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $amount) === 1;
    }

    private function ownedEventQuery(EntityManagerInterface $entityManager, User $staff): QueryBuilder
    {
        return $entityManager->getRepository(Event::class)->createQueryBuilder('event')
            ->join('event.court', 'court')
            ->addSelect('court')
            ->where('court.owner = :staff')
            ->setParameter('staff', $staff);
    }

    private function ownedBookingQuery(EntityManagerInterface $entityManager, User $staff): QueryBuilder
    {
        return $entityManager->getRepository(Booking::class)->createQueryBuilder('booking')
            ->join('booking.court', 'court')
            ->addSelect('court')
            ->join('booking.user', 'user')
            ->addSelect('user')
            ->where('court.owner = :staff')
            ->setParameter('staff', $staff);
    }

    private function ownedJoinQuery(EntityManagerInterface $entityManager, User $staff): QueryBuilder
    {
        return $entityManager->getRepository(EventJoin::class)->createQueryBuilder('eventJoin')
            ->join('eventJoin.event', 'event')
            ->addSelect('event')
            ->join('event.court', 'court')
            ->addSelect('court')
            ->join('eventJoin.user', 'user')
            ->addSelect('user')
            ->where('court.owner = :staff')
            ->setParameter('staff', $staff);
    }
}
