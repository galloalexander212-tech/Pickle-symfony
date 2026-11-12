<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingController extends AbstractController
{
    #[Route('/slots', name: 'app_slots', methods: ['GET'])]
    public function slots(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $courtName = trim((string) $request->query->get('court', ''));
        $date = trim((string) $request->query->get('date', ''));
        $taken = [];

        if ($courtName !== '' && $date !== '') {
            $bookingDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($bookingDate !== false && $bookingDate->format('Y-m-d') === $date) {
                $rows = $entityManager->getRepository(Booking::class)->createQueryBuilder('booking')
                    ->select('booking.bookingTime AS bookingTime')
                    ->where('booking.courtName = :court')
                    ->andWhere('booking.bookingDate = :date')
                    ->andWhere('booking.status != :cancelled')
                    ->setParameter('court', $courtName)
                    ->setParameter('date', $bookingDate)
                    ->setParameter('cancelled', 'cancelled')
                    ->getQuery()
                    ->getScalarResult();

                foreach ($rows as $row) {
                    $time = $row['bookingTime'];
                    $taken[] = $time instanceof \DateTimeInterface ? $time->format('H:i') : substr((string) $time, 0, 5);
                }
            }
        }

        return new JsonResponse(['taken' => $taken]);
    }

    #[Route('/book', name: 'app_book', methods: ['POST'])]
    public function book(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Please log in to book a court.');

            return $this->redirectToRoute('app_login', ['notice' => 'login-first']);
        }

        $action = (string) $request->request->get('action', 'book');
        if ($action === 'cancel') {
            return $this->cancel($request, $entityManager, $user);
        }
        if ($action !== 'book') {
            return $this->redirectToRoute('app_home');
        }

        $courtName = trim((string) $request->request->get('court', ''));
        $date = trim((string) $request->request->get('date', ''));
        $time = trim((string) $request->request->get('time', ''));
        $players = (int) $request->request->get('players', 2);
        $errors = [];

        if ($courtName === '') {
            $errors[] = 'Court is required.';
        }
        if ($date === '') {
            $errors[] = 'Date is required.';
        }
        if ($time === '') {
            $errors[] = 'Time slot is required.';
        }

        if (!in_array($players, [2, 4, 6, 8], true)) {
            $players = 2;
        }

        $timezone = new \DateTimeZone('Asia/Manila');
        $now = new \DateTimeImmutable('now', $timezone);
        $today = $now->setTime(0, 0);
        $bookingDate = $date === '' ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if ($date !== '' && ($bookingDate === false || $bookingDate->format('Y-m-d') !== $date)) {
            $errors[] = 'Date is invalid.';
        } elseif ($bookingDate !== false && $date < $today->format('Y-m-d')) {
            $errors[] = "You can't book a court in the past.";
        }

        if ($courtName !== '' && strlen($courtName) > 100) {
            $errors[] = 'Invalid court.';
        }

        $validSlots = [];
        for ($hour = 6; $hour <= 21; $hour++) {
            $validSlots[] = sprintf('%02d:00', $hour);
        }

        if ($time !== '' && !in_array($time, $validSlots, true)) {
            $errors[] = 'Invalid time slot. Slots run from 6:00 AM to 9:00 PM.';
        }

        if ($date === $today->format('Y-m-d') && $time !== '' && in_array($time, $validSlots, true)) {
            [$hour] = explode(':', $time);
            $slotMinutes = (int) $hour * 60;
            $nowMinutes = (int) $now->format('H') * 60 + (int) $now->format('i');
            if ($slotMinutes <= $nowMinutes) {
                $errors[] = 'That time slot has already passed today.';
            }
        }

        $court = null;
        if ($courtName !== '' && strlen($courtName) <= 100) {
            $court = $entityManager->getRepository(Court::class)->findOneBy(['name' => $courtName]);
            if (!$court instanceof Court) {
                $errors[] = 'Invalid court.';
            }
        }

        if ($errors === [] && $court instanceof Court && $bookingDate instanceof \DateTimeImmutable) {
            $taken = $entityManager->getRepository(Booking::class)->createQueryBuilder('booking')
                ->select('booking.id')
                ->where('booking.courtName = :court')
                ->andWhere('booking.bookingDate = :date')
                ->andWhere('booking.bookingTime = :time')
                ->andWhere('booking.status != :cancelled')
                ->setParameter('court', $courtName)
                ->setParameter('date', $bookingDate)
                ->setParameter('time', new \DateTimeImmutable($date . ' ' . $time, $timezone))
                ->setParameter('cancelled', 'cancelled')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($taken !== null) {
                $this->addFlash('error', 'Sorry, ' . $courtName . ' is already booked on ' . $date . ' at ' . $time . '. Pick another slot.');

                return $this->redirect($this->generateUrl('app_home') . '#booking');
            }
        }

        if ($errors !== []) {
            $this->addFlash('error', implode(' ', $errors));

            return $this->redirect($this->generateUrl('app_home') . '#booking');
        }

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setCourt($court);
        $booking->setCourtName($courtName);
        $booking->setBookingDate($bookingDate);
        $booking->setBookingTime(new \DateTimeImmutable($date . ' ' . $time, $timezone));
        $booking->setPlayers($players);
        $booking->setStatus('pending');

        $entityManager->persist($booking);
        $entityManager->flush();

        $this->addFlash('success', 'Court booked! ' . $courtName . ' on ' . $date . ' at ' . $time . ' for ' . $players . ' players. Waiting for confirmation.');

        return $this->redirectToRoute('app_success', ['booking' => $booking->getId()]);
    }

    #[Route('/success', name: 'app_success', methods: ['GET'])]
    public function success(Request $request, EntityManagerInterface $entityManager): Response
    {
        $bookingId = $request->query->getInt('booking');
        $booking = null;

        if ($bookingId > 0) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                return $this->redirectToRoute('app_login', ['notice' => 'login-first']);
            }

            $booking = $entityManager->getRepository(Booking::class)->findOneBy([
                'id' => $bookingId,
                'user' => $user,
            ]);
            if (!$booking instanceof Booking) {
                return $this->redirectToRoute('app_account');
            }
        }

        return $this->render('booking/success.html.twig', [
            'booking' => $booking,
            'name' => (string) $request->query->get('name', 'player'),
        ]);
    }

    private function cancel(Request $request, EntityManagerInterface $entityManager, User $user): Response
    {
        $bookingId = $request->request->getInt('booking_id');
        $booking = $bookingId > 0 ? $entityManager->getRepository(Booking::class)->find($bookingId) : null;
        if ($booking instanceof Booking && $booking->getUser()?->getId() === $user->getId() && $booking->getStatus() !== 'cancelled') {
            $booking->setStatus('cancelled');
            $entityManager->flush();
            $this->addFlash('success', 'Booking cancelled.');
        } else {
            $this->addFlash('error', 'That booking could not be cancelled.');
        }

        return $this->redirectToRoute('app_account');
    }
}