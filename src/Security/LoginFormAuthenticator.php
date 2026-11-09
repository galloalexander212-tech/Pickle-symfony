<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginFormAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UserRepository $users,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'app_login' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = trim((string) $request->request->get('username', ''));
        $password = (string) $request->request->get('password', '');
        $errors = [];

        if ($identifier === '') {
            $errors['login_id'] = 'Username or email is required.';
        }
        if (trim($password) === '') {
            $errors['login_password'] = 'Password is required.';
        }

        if ($errors !== []) {
            $request->getSession()->set('login_form_errors', $errors);
            throw new CustomUserMessageAuthenticationException(reset($errors));
        }

        if (strlen($identifier) > 100 || strlen($password) > 100) {
            throw new CustomUserMessageAuthenticationException('Wrong username/email or password.');
        }

        return new Passport(
            new UserBadge($identifier, function (string $userIdentifier): UserInterface {
                return $this->users->findOneByUsernameOrEmail($userIdentifier)
                    ?? throw new CustomUserMessageAuthenticationException('Wrong username/email or password.');
            }),
            new CustomCredentials(
                fn (mixed $credentials, UserInterface $user): bool => $this->checkCredentials((string) $credentials, $user),
                $password,
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if ($user instanceof User && $user->isAdmin()) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin'));
        }

        if ($user instanceof User) {
            return new RedirectResponse($this->urlGenerator->generate('app_home'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $session = $request->getSession();
        $errors = $session->get('login_form_errors', []);
        if (!is_array($errors) || $errors === []) {
            $errors = ['login_password' => $exception->getMessageKey()];
        }

        $session->set('login_form_errors', $errors);
        $session->set('login_identifier', trim((string) $request->request->get('username', '')));

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function checkCredentials(string $password, UserInterface $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $lockedUntil = $user->getLockedUntil();
        if ($lockedUntil !== null && $lockedUntil->getTimestamp() > time()) {
            $minutes = (int) ceil(($lockedUntil->getTimestamp() - time()) / 60);
            throw new CustomUserMessageAuthenticationException(
                'Too many failed attempts. Try again in ' . $minutes . ' minute(s).',
            );
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            $attempts = $user->getFailedAttempts() + 1;
            $user->setFailedAttempts($attempts);

            if ($attempts >= 5) {
                $user->setLockedUntil(new \DateTimeImmutable('+15 minutes'));
                $this->entityManager->flush();
                throw new CustomUserMessageAuthenticationException(
                    'Too many failed attempts. Account locked for 15 minutes.',
                );
            }

            $this->entityManager->flush();
            $remaining = 5 - $attempts;
            throw new CustomUserMessageAuthenticationException(
                'Wrong username/email or password. (' . $remaining . ' attempt(s) left)',
            );
        }

        $user->setFailedAttempts(0);
        $user->setLockedUntil(null);
        $this->entityManager->flush();

        return true;
    }
}