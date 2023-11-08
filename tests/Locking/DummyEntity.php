<?php

declare(strict_types=1);

/**
 * Copyright (c) 2023 Maksim Tugaev
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/tugmaks/doctrine-walkers
 */

namespace Tugmaks\DoctrineWalkersTest\Locking;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()] #[ORM\Table(name: 'de_tbl')]
class DummyEntity
{
    #[ORM\Id()] #[ORM\Column()]
    public string $id;

    #[ORM\Column()]
    public string $name;
}
