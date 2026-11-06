<?php

declare(strict_types=1);

/**
 * Copyright (c) 2025-2026 Maksim Tyugaev
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/tugmaks/doctrine-walkers
 */

namespace Tugmaks\DoctrineWalkersTest\Integration\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table(name: 'it_players')]
class Player
{
    #[ORM\Column()]
    #[ORM\GeneratedValue()]
    #[ORM\Id()]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column()]
        public string $name,
        #[ORM\Column()]
        public string $country,
        #[ORM\Column(nullable: true)]
        public ?int $score = null,
        #[ORM\JoinColumn(name: 'team_id')]
        #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'players')]
        public ?Team $team = null,
    ) {
    }
}
