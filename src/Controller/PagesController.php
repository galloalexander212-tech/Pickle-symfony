<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\Event;
use App\Entity\EventJoin;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PagesController extends AbstractController
{
    #[Route('/about', name: 'app_about')]
    public function about(EntityManagerInterface $em): Response
    {
        return $this->render('pages/about.html.twig', [
            'playerCount' => $em->getRepository(User::class)->count([]),
            'courtCount' => $em->getRepository(Court::class)->count([]),
            'eventCount' => $em->getRepository(Event::class)->count([]),
            'bookingCount' => $em->getRepository(Booking::class)->count([]),
        ]);
    }

    #[Route('/support', name: 'app_support')]
    public function support(): Response
    {
        return $this->render('pages/support.html.twig');
    }

    #[Route('/courts', name: 'app_courts')]
    public function courts(Request $request, EntityManagerInterface $em): Response
    {
        $location = trim((string) $request->query->get('location', ''));
        $date = trim((string) $request->query->get('date', ''));
        $players = (int) $request->query->get('players', 0);

        if (!in_array($players, [2, 4, 6, 8], true)) {
            $players = 0;
        }

        $today = (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila')))->format('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today) {
            $date = '';
        }

        $courtQuery = $em->getRepository(Court::class)->createQueryBuilder('court');
        if ($location !== '') {
            $courtQuery
                ->where('court.location LIKE :location')
                ->setParameter('location', '%' . $location . '%');
        }

        $courts = $courtQuery
            ->orderBy('court.name', 'ASC')
            ->getQuery()
            ->getResult();

        $bookedOnDate = [];
        if ($date !== '') {
            $bookings = $em->getRepository(Booking::class)->createQueryBuilder('booking')
                ->select('booking.courtName AS courtName, COUNT(booking.id) AS taken')
                ->where('booking.bookingDate = :date')
                ->andWhere('booking.status != :cancelled')
                ->groupBy('booking.courtName')
                ->setParameter('date', $date)
                ->setParameter('cancelled', 'cancelled')
                ->getQuery()
                ->getArrayResult();

            foreach ($bookings as $booking) {
                $bookedOnDate[$booking['courtName']] = (int) $booking['taken'];
            }
        }

        return $this->render('courts/index.html.twig', [
            'courts' => $courts,
            'location' => $location,
            'date' => $date,
            'players' => $players,
            'bookedOnDate' => $bookedOnDate,
            'hasFilters' => $location !== '' || $date !== '' || $players > 0,
        ]);
    }

    #[Route('/openplays', name: 'app_openplays')]
    public function openplays(EntityManagerInterface $em): Response
    {
        return $this->render('event/openplays.html.twig', [
            'openPlays' => $this->upcomingEvents($em, 'open_play'),
            'joinedIds' => $this->joinedEventIds($em),
        ]);
    }

    #[Route('/tournaments', name: 'app_tournaments')]
    public function tournaments(EntityManagerInterface $em): Response
    {
        return $this->render('event/tournaments.html.twig', [
            'tournaments' => $this->upcomingEvents($em, 'tournament'),
            'joinedIds' => $this->joinedEventIds($em),
        ]);
    }

    private function upcomingEvents(EntityManagerInterface $em, string $type): array
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila'));

        return $em->getRepository(Event::class)->createQueryBuilder('event')
            ->leftJoin('event.eventJoins', 'eventJoin')
            ->addSelect('eventJoin')
            ->where('event.type = :type')
            ->andWhere('event.eventDate >= :today')
            ->setParameter('type', $type)
            ->setParameter('today', $today)
            ->orderBy('event.eventDate', 'ASC')
            ->addOrderBy('event.eventTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function joinedEventIds(EntityManagerInterface $em): array
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $rows = $em->createQueryBuilder()
            ->select('IDENTITY(eventJoin.event) AS eventId')
            ->from(EventJoin::class, 'eventJoin')
            ->where('eventJoin.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['eventId'], $rows);
    }
}