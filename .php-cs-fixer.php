<?php

declare(strict_types=1);

use Ergebnis\PhpCsFixer\Config;

$header = <<<EOF
Copyright (c) 2025 Maksim Tugaev

For the full copyright and license information, please view
the LICENSE file that was distributed with this source code.

@see https://github.com/tugmaks/doctrine-walkers
EOF;


$ruleSet = Config\RuleSet\Php81::create()->withHeader($header);

$config = Config\Factory::fromRuleSet($ruleSet);

$config->getFinder()->in(__DIR__)->exclude('var');
$config->setCacheFile(__DIR__ . '/.build/php-cs-fixer/.php-cs-fixer.cache');

return $config;