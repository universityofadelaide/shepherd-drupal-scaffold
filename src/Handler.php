<?php

namespace UniversityOfAdelaide\ShepherdDrupalScaffold;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\RemoteFilesystem;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

class Handler
{

    /**
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * Handler constructor.
     *
     * @param Composer $composer
     * @param IOInterface $io
     */
    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
    * Post update command event to execute the scaffolding.
    *
    * @param \Composer\Script\Event $event
    */
    public function onPostCmdEvent(\Composer\Script\Event $event)
    {
        $this->downloadScaffold();
    }

    /**
     * Downloads Shepherd Drupal scaffold files.
     */
    public function downloadScaffold()
    {
        $source = 'https://raw.githubusercontent.com/universityofadelaide/shepherd-drupal-scaffold/{version}/{path}';
        $filenames = [
            'RoboFileBase.php'
        ];
        $version = 'master';
        $destination = dirname($this->composer->getConfig()
            ->get('vendor-dir'));

        $fetcher = new FileFetcher(
            new RemoteFilesystem($this->io),
            $source,
            $filenames
        );
        $fetcher->fetch($version, $destination);
    }
}
