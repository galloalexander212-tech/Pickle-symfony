<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\EventJoin;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventController extends AbstractController
{
    #[Route('/events/{id}/join', name: 'app_event_join', methods: ['POST'])]
    public function join(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Please log in to join events.');

            return $this->redirectToRoute('app_login', ['notice' => 'login-event']);
        }

        $event = $entityManager->getRepository(Event::class)->find($id);
        if (!$event instanceof Event) {
            $this->addFlash('error', 'That event could not be found.');

            return $this->redirectToRoute('app_home');
        }

        $anchor = $event->getType() === 'open_play' ? 'openPlayList' : 'tournamentList';
        if ($request->request->get('action', 'join') !== 'join') {
            $this->addFlash('error', 'Invalid event action.');

            return $this->redirectToRoute('app_home');
        }

        if ($event->getEventDate() < new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila'))) {
            $this->addFlash('error', 'That event has already happened.');

            return $this->redirect($this->generateUrl('app_home') . '#' . $anchor);
        }

        $joins = $entityManager->getRepository(EventJoin::class);
        if ($joins->findOneBy(['event' => $event, 'user' => $user]) !== null) {
            $this->addFlash('error', 'You already joined ' . $event->getTitle() . '.');

            return $this->redirect($this->generateUrl('app_home') . '#' . $anchor);
        }

        if ($event->getEventJoins()->count() >= $event->getMaxPlayers()) {
            $this->addFlash('error', 'Sorry, ' . $event->getTitle() . ' is full!');

            return $this->redirect($this->generateUrl('app_home') . '#' . $anchor);
        }

        $join = new EventJoin();
        $join->setEvent($event);
        $join->setUser($user);
        $entityManager->persist($join);

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            $this->addFlash('error', 'You already joined ' . $event->getTitle() . '.');

            return $this->redirect($this->generateUrl('app_home') . '#' . $anchor);
        }

        $this->addFlash('success', "You're in! See you at " . $event->getTitle() . '.');

        return $this->redirectToRoute('app_account');
    }

    #[Route('/events/{id}/leave', name: 'app_event_leave', methods: ['POST'])]
    public function leave(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Please log in to join events.');

            return $this->redirectToRoute('app_login', ['notice' => 'login-event']);
        }

        $event = $entityManager->getRepository(Event::class)->find($id);
        if (!$event instanceof Event) {
            $this->addFlash('error', 'That event could not be found.');

            return $this->redirectToRoute('app_home');
        }

        $join = $entityManager->getRepository(EventJoin::class)->findOneBy([
            'event' => $event,
            'user' => $user,
        ]);

        if ($join instanceof EventJoin) {
            $entityManager->remove($join);
            $entityManager->flush();
            $this->addFlash('success', 'You left ' . $event->getTitle() . '.');
        } else {
            $this->addFlash('error', 'You have not joined ' . $event->getTitle() . '.');
        }

        return $this->redirectToRoute('app_account');
    }
}