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

namespace Tugmaks\DoctrineWalkersTest\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Tugmaks\DoctrineWalkersTest\Integration\Entity\Player;
use Tugmaks\DoctrineWalkersTest\Integration\Entity\Team;

/**
 * Runs queries against a real database from the DATABASE_URL environment variable.
 * Without it the tests are skipped locally and fail in CI, so they cannot be lost silently.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected EntityManager $entityManager;
    private static bool $schemaCreated = false;

    protected function setUp(): void
    {
        $this->entityManager = new EntityManager(self::createConnection(), self::createConfiguration());

        if (!self::$schemaCreated) {
            $schemaTool = new SchemaTool($this->entityManager);
            $metadata = [
                $this->entityManager->getClassMetadata(Team::class),
                $this->entityManager->getClassMetadata(Player::class),
            ];

            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
            self::$schemaCreated = true;
        }

        // Data is committed, not rolled back: locking tests need it visible to a second connection.
        $this->entityManager->getConnection()->executeStatement('TRUNCATE it_players, it_teams RESTART IDENTITY CASCADE');
    }

    protected function tearDown(): void
    {
        // Not initialized when setUp() skipped the test.
        if (!isset($this->entityManager)) {
            return;
        }

        $this->entityManager->close();
        $this->entityManager->getConnection()->close();
    }

    /**
     * A separate connection, e.g. to hold row locks while the tested query runs.
     */
    protected static function createConnection(): Connection
    {
        $url = \getenv('DATABASE_URL');

        if (!\is_string($url) || '' === $url) {
            if (false !== \getenv('CI')) {
                self::fail('DATABASE_URL must be set for integration tests in CI.');
            }

            self::markTestSkipped('DATABASE_URL is not set. Run "docker compose run --rm php vendor/bin/phpunit --testsuite integration".');
        }

        return DriverManager::getConnection((new DsnParser())->parse($url));
    }

    protected function persist(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private static function createConfiguration(): Configuration
    {
        $config = new Configuration();

        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        } else {
            $config->setProxyDir(\sys_get_temp_dir() . '/doctrine-walkers-proxies');
            $config->setProxyNamespace('Tugmaks\DoctrineWalkersTest\Integration\Proxies');
            $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_EVAL);
        }

        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__ . '/Entity']));

        return $config;
    }
}
