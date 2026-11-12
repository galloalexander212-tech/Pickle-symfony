<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'EVENTS')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, options: ['default' => 'open_play'])]
    private string $type = 'open_play';

    #[ORM\Column(length: 150)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 150)]
    private ?string $location = null;

    #[ORM\Column(name: 'event_date', type: 'date_immutable')]
    private ?\DateTimeImmutable $eventDate = null;

    #[ORM\Column(name: 'event_time', type: 'time_immutable')]
    private ?\DateTimeImmutable $eventTime = null;

    #[ORM\Column(name: 'max_players', options: ['default' => 8])]
    private int $maxPlayers = 8;

    #[ORM\Column(name: 'entrance_fee', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $entranceFee = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $prize = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'createdEvents')]
    #[ORM\JoinColumn(name: 'created_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $creator = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, EventJoin> */
    #[ORM\OneToMany(targetEntity: EventJoin::class, mappedBy: 'event')]
    private Collection $eventJoins;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->eventJoins = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getEventDate(): ?\DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function setEventDate(\DateTimeImmutable $eventDate): static
    {
        $this->eventDate = $eventDate;

        return $this;
    }

    public function getEventTime(): ?\DateTimeImmutable
    {
        return $this->eventTime;
    }

    public function setEventTime(\DateTimeImmutable $eventTime): static
    {
        $this->eventTime = $eventTime;

        return $this;
    }

    public function getMaxPlayers(): int
    {
        return $this->maxPlayers;
    }

    public function setMaxPlayers(int $maxPlayers): static
    {
        $this->maxPlayers = $maxPlayers;

        return $this;
    }

    public function getEntranceFee(): ?string
    {
        return $this->entranceFee;
    }

    public function setEntranceFee(?string $entranceFee): static
    {
        $this->entranceFee = $entranceFee;

        return $this;
    }

    public function getPrize(): ?string
    {
        return $this->prize;
    }

    public function setPrize(?string $prize): static
    {
        $this->prize = $prize;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;

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

    /** Live count of joins — replaces the old SQL COUNT(joined_count) */
    public function getJoinedCount(): int
    {
        return $this->eventJoins->count();
    }

    /** @return Collection<int, EventJoin> */
    public function getEventJoins(): Collection
    {
        return $this->eventJoins;
    }

    public function addEventJoin(EventJoin $eventJoin): static
    {
        if (!$this->eventJoins->contains($eventJoin)) {
            $this->eventJoins->add($eventJoin);
            $eventJoin->setEvent($this);
        }

        return $this;
    }

    public function removeEventJoin(EventJoin $eventJoin): static
    {
        if ($this->eventJoins->removeElement($eventJoin)) {
            if ($eventJoin->getEvent() === $this) {
                $eventJoin->setEvent(null);
            }
        }

        return $this;
    }
}