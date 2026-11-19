<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

/**
 * Test bootstrap.
 *
 * The unit suite runs without booting Craft: what it covers — the custom attribute and URL
 * guards, the tree building, visibility rule matching, the upgrade migration's class map, the
 * enums — is plain PHP over plain data. Everything that needs a database or a request is checked
 * live in the plugin-testing-craft6 harness (see tests/README.md).
 */

require_once __DIR__.'/../vendor/autoload.php';

ini_set('date.timezone', 'UTC');
date_default_timezone_set('UTC');

// Just enough container for the facades and validators the pure code touches.
$container = new Container;
Container::setInstance($container);
Facade::setFacadeApplication($container); // @phpstan-ignore argument.type
$translator = new Translator(new ArrayLoader, 'en');
$container->instance('translator', $translator);
$container->instance(Illuminate\Contracts\Validation\Factory::class, new Factory($translator, $container));
$container->alias(Illuminate\Contracts\Validation\Factory::class, 'validator');
$container->instance('events', new Dispatcher($container));
