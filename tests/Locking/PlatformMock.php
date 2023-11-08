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

use Doctrine\DBAL\Platforms\AbstractPlatform;

final class PlatformMock extends AbstractPlatform
{
    public function getBooleanTypeDeclarationSQL(array $column): string
    {
        return '';
    }

    public function getIntegerTypeDeclarationSQL(array $column): string
    {
        return '';
    }

    public function getBigIntTypeDeclarationSQL(array $column): string
    {
        return '';
    }

    public function getSmallIntTypeDeclarationSQL(array $column): string
    {
        return '';
    }

    public function getClobTypeDeclarationSQL(array $column): string
    {
        return '';
    }

    public function getBlobTypeDeclarationSQL(array $column): string
    {
        return '';
    }

    public function getName(): string
    {
        return self::class;
    }

    public function getCurrentDatabaseExpression(): string
    {
        return '';
    }

    protected function _getCommonIntegerTypeDeclarationSQL(array $column): string
    {
        return '';
    }

    protected function initializeDoctrineTypeMappings(): void
    {
    }
}
