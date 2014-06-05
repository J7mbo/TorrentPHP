<?php

namespace TorrentPHP;

/**
 * Class TorrentFactory
 *
 * Simple class to Dependency Inject around your application for creating Torrent objects
 *
 * @package TorrentPHP
 */
class TorrentFactory
{
    /**
     * @see Torrent::__construct()
     */
    public function build($hashString, $name, $size)
    {
        return new Torrent($hashString, $name, $size);
    }
} 