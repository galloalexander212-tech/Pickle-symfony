<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class UserRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findOneByUsernameOrEmail(string $identifier): ?User
    {
        return $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('user')
            ->where('user.username = :identifier OR user.email = :identifier')
            ->setParameter('identifier', $identifier)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}