<?php

declare(strict_types=1);

/**
 * Copyright (c) 2025 Maksim Tugaev
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/tugmaks/doctrine-walkers
 */

namespace Tugmaks\DoctrineWalkersTest;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

abstract class AbstractWalkerTestCase extends TestCase
{
    protected EntityManager $entityManager;
    protected Connection&MockObject $connectionMock;
    protected Configuration $configuration;

    protected function setUp(): void
    {
        $config = new Configuration();
        $config->setProxyNamespace('Tmp\Doctrine\Tests\Proxies');
        $config->setProxyDir('/tmp/doctrine');
        $config->setAutoGenerateProxyClasses(false);
        $config->setSecondLevelCacheEnabled(false);
        $config->setMetadataDriverImpl(new AttributeDriver([]));

        $connectionMock = $this->createMock(Connection::class);

        $connectionMock->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());

        $this->connectionMock = $connectionMock;
        $this->configuration = $config;

        $this->entityManager = new EntityManager($connectionMock, $config);
    }
}
