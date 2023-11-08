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