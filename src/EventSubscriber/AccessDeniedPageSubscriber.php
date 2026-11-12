<?php

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Twig\Environment;

final class AccessDeniedPageSubscriber
{
    public function __construct(
        private Environment $twig,
        private Security $security,
    ) {
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 2)]
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getThrowable();
        if (!$exception instanceof AccessDeniedException && !$exception instanceof AccessDeniedHttpException) {
            return;
        }
        if ($this->security->getUser() === null) {
            return;
        }

        $event->setResponse(new Response(
            $this->twig->render('error/403.html.twig'),
            Response::HTTP_FORBIDDEN,
        ));
    }
}
