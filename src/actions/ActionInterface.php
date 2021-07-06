<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold\actions;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Script\Event;

interface ActionInterface
{
    /**
     * @return static
     */
    public static function create(Composer $composer, IOInterface $io);

    public function onEvent(Event $event): void;
}
