<?php

declare(strict_types=1);

/** @var Symfony\Component\Finder\Finder $finder */
$finder = Isolated\Symfony\Component\Finder\Finder::class;

return [
    'prefix' => 'Ollimport\MailerLiteGroups\Vendor',
    'finders' => [
      $finder::create()->files()->in('src'),
	    $finder::create()
	          ->files()
	          ->ignoreVCS( true )
            ->notName('/LICENSE|.*\\.md|.*\\.dist|Makefile|composer\\.json|composer\\.lock/')
	          ->exclude( [
		          'doc',
		          'test',
		          'test_old',
		          'tests',
		          'Tests',
		          'vendor-bin',
	          ] )
            ->in(['src', 'vendor/wpdesk/wp-wpdesk-license', 'vendor/psr/log']), 
        $finder::create()->append([
            'composer.json',
'wpdesk-integration.php'
        ])
  ],
];
