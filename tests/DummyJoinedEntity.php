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

use Doctrine\ORM\Mapping as ORM;

#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['parent' => DummyJoinedEntity::class, 'child' => DummyJoinedChildEntity::class])]
#[ORM\Entity()]
#[ORM\InheritanceType('JOINED')]
#[ORM\Table(name: 'de_joined_tbl')]
class DummyJoinedEntity
{
    #[ORM\Column()]
    #[ORM\Id()]
    public string $id;

    #[ORM\Column()]
    public string $name;
}
