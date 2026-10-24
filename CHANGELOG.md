CHANGELOG
========
2.6 Unreleased
-----
* Add psalm CI workflow
* `NULLS` walker now throws `NullsWalkerException` when hint refers to a field that is not used in `ORDER BY` instead of silently ignoring it (#19)
* `NULLS` walker now supports result variable aliases in `ORDER BY`, e.g. `SELECT (...) AS foo ... ORDER BY foo` with hint `['foo' => NULLS::FIRST]` (#18)

2.5
-----
* `DISTINCT ON` walker

2.4
-----
* Fix `TABLESAMPLE` walker: throw `TablesampleWalkerException` when hint is missing an entity name instead of silent crash
* Ensure 0 and 100 are not allowed in `TABLESAMPLE`
* Fix `TABLESAMPLE` tests
* `WITH TIES` walker now uses own finalizer

2.3
---

* `RETURNING` walker for SELECT/UPDATE/DELETE DQL queries
* Run CI on php 8.5
* Update README
* Increase phpstan level from 9 to 10

2.2
---

* `WITH TIES` Walker

2.1
---

* Drop support for php 8.1
* Migrate to phpunit 11

2.0
---

* Add support for doctrine/dbal ^4.0 and doctrine/orm ^3.0
* Drop support for doctrine/orm ^2

1.3
---

* Walkers now implement Doctrine\ORM\Query\OutputWalker interface. See https://github.com/doctrine/orm/pull/11188/

1.2
---

* `TABLESAMPLE` walker (SYSTEM/BERNOULLI)

1.1
---

* `Nulls` walker (NULLS LAST/FIRST)

1.0
---

* `Locking` walker (SELECT FOR UPDATE SKIP LOCKED)
