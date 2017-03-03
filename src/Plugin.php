<?php
/**
 * @file
 * Contains UniversityOfAdelaide\ShepherdDrupalScaffold\Plugin.
 */

namespace UniversityOfAdelaide\ShepherdDrupalScaffold;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Plugin\PluginInterface;
use Composer\Util\RemoteFilesystem;
use DrupalComposer\DrupalScaffold\FileFetcher;

/**
 * Composer plugin for handling Shepherd Drupal scaffold.
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * Script callback for putting in composer scripts to download the
     * scaffold files.
     *
     * @param \Composer\Script\Event $event
     */
    public static function scaffold(\Composer\Script\Event $event)
    {
        $source = 'https://raw.githubusercontent.com/universityofadelaide/shepherd-drupal-scaffold/{version}/{path}';
        $filenames = [
            'RoboFileBase.php'
        ];
        $version = 'master';
        $destination = dirname($event->getComposer()->getConfig()
            ->get('vendor-dir'));

        $fetcher = new FileFetcher(
            new RemoteFilesystem($event->getIO()),
            $source,
            $filenames
        );
        $fetcher->fetch($version, $destination);
    }
}
