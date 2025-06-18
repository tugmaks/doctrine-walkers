[![codecov](https://codecov.io/gh/tugmaks/doctrine-walkers/graph/badge.svg?token=4YXA0059QT)](https://codecov.io/gh/tugmaks/doctrine-walkers)
# Doctrine walkers

## Installation

```bash
composer require tugmaks/doctrine-walkers
```

## Locking walker
### Example
```php
$query = $this->entityManager->createQuery('SELECT u FROM App\Entity\User u WHERE id = 1');

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, LockingWalker::class);
$query->setHint(LockingWalker::LOCKING_CLAUSE, new LockingClause(LockStrength::UPDATE, Option::SKIP_LOCKED));

$query->getSQL();
```
Output:
```sql
SELECT u0_.id AS id_0, u0_.name AS name_1 FROM users u0_ WHERE u0_.id = 1 FOR UPDATE SKIP LOCKED
```

## NULLS walker
### Example
```php
$query = $this->entityManager->createQuery('SELECT u FROM App\Entity\User u ORDER BY u.name DESC');

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, NullsWalker::class);
$query->setHint(NullsWalkers::NULLS_RULE, ['u.name' => NULLS::LAST]);

$query->getSQL();
```
Output:
```sql
SELECT u0_.id AS id_0, u0_.name AS name_1 FROM users u0_ ORDER BY u0_.name DESC NULLS LAST
```

## Tablesample walker
### Example
```php
$query = $this->entityManager->createQuery('SELECT u FROM App\Entity\User u ORDER BY u.name DESC');

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, TablesampleWalker::class);
$query->setHint(TablesampleWalker::TABLESAMPLE_RULE, [User::class => new Tablesample(TablesampleMethod::BERNOULLI, 0.1) ]);

$query->getSQL();
```
Output:
```sql
SELECT u0_.id AS id_0, u0_.name AS name_1 FROM users u0_ TABLESAMPLE BERNOULLI(0.1) ORDER BY u0_.name DESC
```

## `WITH TIES` walker
### Example
```php
$query = $this->entityManager->createQuery('SELECT u FROM App\Entity\User u ORDER BY u.name DESC')->setMaxResults(5);

$query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, WithTiesWalker::class);

$query->getSQL();
```
Output:
```sql
SELECT u0_.id AS id_0, u0_.name AS name_1 FROM users u0_ ORDER BY u0_.name DESC FETCH NEXT 5 ROWS WITH TIES
```