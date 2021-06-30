<?php

namespace UniversityOfAdelaide\ShepherdDrupalScaffold;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

/**
 * Composer plugin for handling Shepherd Drupal scaffold.
 */
class ShepherdDrupalScaffoldPlugin implements PluginInterface, EventSubscriberInterface
{

    protected Handler $handler;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->handler = new Handler($composer, $io);
    }

    public function deactivate(Composer $composer, IOInterface $io) {
    }

    public function uninstall(Composer $composer, IOInterface $io) {
    }

    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_INSTALL_CMD => 'postCmd',
            ScriptEvents::POST_UPDATE_CMD => 'postCmd',
        );
    }

    /**
    * Post command event callback.
    */
    public function postCmd(Event $event)
    {
        $this->handler->onPostCmdEvent($event);
    }


    /**
     * Script callback for putting in composer scripts to download the
     * scaffold files.
    */
    public static function scaffold(Event $event)
    {
        $handler = new Handler($event->getComposer(), $event->getIO());
        $handler->onPostCmdEvent($event);
    }
}
