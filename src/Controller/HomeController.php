<?php

namespace App\Controller;

use App\Entity\Court;
use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {
        $openPlays   = $this->upcomingEvents($em, 'open_play');
        $tournaments = $this->upcomingEvents($em, 'tournament');

        $courts = $em->getRepository(Court::class)->findBy([], ['name' => 'ASC']);

        $playerCount = $em->getRepository(User::class)->count([]);

        $joinedIds = [];
        $user = $this->getUser();
        if ($user instanceof User) {
            foreach ($user->getEventJoins() as $join) {
                $joinedIds[] = $join->getEvent()->getId();
            }
        }

        return $this->render('home/index.html.twig', [
            'openPlays'   => $openPlays,
            'tournaments' => $tournaments,
            'courts'      => $courts,
            'joinedIds'   => $joinedIds,
            'playerCount' => $playerCount,
        ]);
    }

    /** Old SQL: WHERE type = ? AND event_date >= CURDATE() ORDER BY date, time LIMIT 6 */
    private function upcomingEvents(EntityManagerInterface $em, string $type, int $limit = 6): array
    {
        return $em->createQueryBuilder()
            ->select('e')
            ->from(Event::class, 'e')
            ->where('e.type = :type')
            ->setParameter('type', $type)
            ->andWhere('e.eventDate >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('e.eventDate', 'ASC')
            ->addOrderBy('e.eventTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}