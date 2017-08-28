<?php
/**
 * @file
 * Contains \Robo\RoboFile.
 *
 * Implementation of class for Robo - http://robo.li/
 *
 * You may override methods provided by RoboFileBase.php in this file.
 * Configuration overrides should be made in the constructor.
 */

include_once 'RoboFileBase.php';

/**
 * Class RoboFile.
 */
class RoboFile extends RoboFileBase
{

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct();
        // Put project specific overrides here, below the parent constructor.
    }

    /**
     * {@inheritdoc}
     */
    protected function getDrupalProfile()
    {
        // Replace this with the profile of your choice.
        return "standard";
    }
}
