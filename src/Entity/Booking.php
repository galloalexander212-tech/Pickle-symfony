<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'BOOKINGS')]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Court::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(name: 'court_id', nullable: true, onDelete: 'SET NULL')]
    private ?Court $court = null;

    #[ORM\Column(name: 'court', length: 100)]
    private ?string $courtName = null;

    #[ORM\Column(name: 'booking_date', type: 'date_immutable')]
    private ?\DateTimeImmutable $bookingDate = null;

    #[ORM\Column(name: 'booking_time', type: 'time_immutable')]
    private ?\DateTimeImmutable $bookingTime = null;

    #[ORM\Column(options: ['default' => 2])]
    private int $players = 2;

    #[ORM\Column(length: 20, options: ['default' => 'pending'])]
    private string $status = 'pending';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCourt(): ?Court
    {
        return $this->court;
    }

    public function setCourt(?Court $court): static
    {
        $this->court = $court;

        return $this;
    }

    public function getCourtName(): ?string
    {
        return $this->courtName;
    }

    public function setCourtName(string $courtName): static
    {
        $this->courtName = $courtName;

        return $this;
    }

    public function getBookingDate(): ?\DateTimeImmutable
    {
        return $this->bookingDate;
    }

    public function setBookingDate(\DateTimeImmutable $bookingDate): static
    {
        $this->bookingDate = $bookingDate;

        return $this;
    }

    public function getBookingTime(): ?\DateTimeImmutable
    {
        return $this->bookingTime;
    }

    public function setBookingTime(\DateTimeImmutable $bookingTime): static
    {
        $this->bookingTime = $bookingTime;

        return $this;
    }

    public function getPlayers(): int
    {
        return $this->players;
    }

    public function setPlayers(int $players): static
    {
        $this->players = $players;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
