<?php

namespace TorrentPHP;

/**
 * Class File
 *
 * Represents a single file within a Torrent download
 *
 * @package TorrentPHP
 */
class File
{
    /**
     * @var int Size of the file in bytes
     */
    private $size = 0;

    /**
     * @var string The file name
     */
    private $name = '';

    /**
     * Get file name
     *
     * @return string The file name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get file size
     *
     * @return int The file size in bytes
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @constructor
     *
     * @param string $name        The name of the file
     * @param int    $sizeInBytes The size of the file
     *
     * @throws \InvalidArgumentException When an invalid size given
     */
    public function __construct($name, $sizeInBytes)
    {
        if (is_int($sizeInBytes) && $sizeInBytes > -1)
        {
            $this->name = $name;
            $this->size = $sizeInBytes;
        }
        else
        {
            throw new \InvalidArgumentException(
                'Invalid file size provided. Size should be bigger than "-1" but "%s" given', $sizeInBytes
            );
        }
    }
} 