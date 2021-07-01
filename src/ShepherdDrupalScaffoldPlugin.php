<?php

declare(strict_types=1);

namespace UniversityOfAdelaide\ShepherdDrupalScaffold;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use UniversityOfAdelaide\ShepherdDrupalScaffold\actions\ActionInterface;
use UniversityOfAdelaide\ShepherdDrupalScaffold\actions\Directories;
use UniversityOfAdelaide\ShepherdDrupalScaffold\actions\DrupalSettings;
use UniversityOfAdelaide\ShepherdDrupalScaffold\actions\GitIgnore;
use UniversityOfAdelaide\ShepherdDrupalScaffold\actions\ScaffoldFiles;

/**
 * Composer plugin for handling Shepherd Drupal scaffold.
 */
class ShepherdDrupalScaffoldPlugin implements PluginInterface, EventSubscriberInterface
{
    protected IOInterface $io;
    protected Composer $composer;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'postCmd',
            ScriptEvents::POST_UPDATE_CMD => 'postCmd',
        ];
    }

    /**
     * Post command event callback.
     */
    public function postCmd(Event $event)
    {
        foreach ([
            Directories::class,
            DrupalSettings::class,
            GitIgnore::class,
            ScaffoldFiles::class,
        ] as $actionClass) {
            $action = $actionClass::create($this->io, $this->composer);
            assert($action instanceof ActionInterface);
            $action->onEvent($event);
        }
    }
}
