<?php

declare(strict_types = 1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()->in('src');
$config = new Config();

return $config
  ->setFinder($finder)
  ->setRules([
    '@Symfony' => true,
    'strict_param' => true,
    'array_syntax' => ['syntax' => 'short'],
    'concat_space' => ['spacing' => 'one'],
    'no_superfluous_phpdoc_tags' => true,
    'phpdoc_align' => false,
    'phpdoc_no_useless_inheritdoc' => true,
  ]);
