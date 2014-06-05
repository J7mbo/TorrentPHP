<?php

namespace TorrentPHP;

/**
 * Class FileFactory
 *
 * Simple class to Dependency Inject around your application for creating Torrent objects
 *
 * @package TorrentPHP
 */
class FileFactory
{
    /**
     * @see File::__construct()
     */
    public function build($name, $sizeInBytes)
    {
        return new File($name, $sizeInBytes);
    }
} 