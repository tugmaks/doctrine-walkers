CHANGELOG
=========
2.2
---

* WITH TIES Walker

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

* TABLESAMPLE walker (SYSTEM/BERNOULLI)

1.1
---

* Nulls walker (NULLS LAST/FIRST)

1.0
---

* Locking walker (SELECT FOR UPDATE SKIP LOCKED)
