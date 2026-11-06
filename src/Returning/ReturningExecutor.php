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

namespace Tugmaks\DoctrineWalkers\Returning;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\Query\Exec\AbstractSqlExecutor;

/**
 * Runs UPDATE/DELETE ... RETURNING as a query, so Doctrine hydrates the returned rows
 * instead of returning the affected row count.
 */
final class ReturningExecutor extends AbstractSqlExecutor
{
    public function __construct(string $sql)
    {
        $this->sqlStatements = $sql;
    }

    public function execute(Connection $conn, array $params, array $types): Result
    {
        if ($conn instanceof PrimaryReadReplicaConnection) {
            $conn->ensureConnectedToPrimary();
        }

        \assert(\is_string($this->sqlStatements));

        // The result cache profile is ignored on purpose: a cache hit would skip the modification.
        return $conn->executeQuery($this->sqlStatements, $params, $types);
    }
}
