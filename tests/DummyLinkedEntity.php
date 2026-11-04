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

namespace Tugmaks\DoctrineWalkersTest;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entity with associations, kept separate from DummyEntity so that SELECT d in other walker tests stays unchanged.
 */
#[ORM\Entity()]
#[ORM\Table(name: 'de_linked_tbl')]
class DummyLinkedEntity
{
    #[ORM\Column()]
    #[ORM\Id()]
    public string $id;

    #[ORM\Column()]
    public int $iq;

    #[ORM\JoinColumn(name: 'related_id')]
    #[ORM\ManyToOne(targetEntity: DummyRelatedEntity::class)]
    public ?DummyRelatedEntity $related = null;

    #[ORM\JoinColumn(name: 'composite_first_id', referencedColumnName: 'firstId')]
    #[ORM\JoinColumn(name: 'composite_second_id', referencedColumnName: 'secondId')]
    #[ORM\ManyToOne(targetEntity: DummyCompositeKeyEntity::class)]
    public ?DummyCompositeKeyEntity $composite = null;

    /**
     * @var Collection<int, DummyRelatedEntity>
     */
    #[ORM\JoinTable(name: 'de_linked_related_tbl')]
    #[ORM\ManyToMany(targetEntity: DummyRelatedEntity::class)]
    public Collection $relatedMany;
}
