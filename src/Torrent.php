<?php

namespace TorrentPHP;

/**
 * Class Torrent
 *
 * Represents the final torrent object provided by TorrentPHP.
 *
 * Status constants represent the statuses commonly used clients. Anything else can be set using setStatus().
 *
 * @package TorrentPHP
 */
class Torrent
{
    /**
     * Torrent is downloading
     */
    const STATUS_DOWNLOADING = 'downloading';

    /**
     * Torrent is seeding
     */
    const STATUS_SEEDING = 'seeding';

    /**
     * Torrent is stopped or paused
     */
    const STATUS_PAUSED = 'paused';

    /**
     * Torrent is complete
     */
    const STATUS_COMPLETE = 'complete';

    /**
     * @var string The torrent hashString which can be used to uniquely identify this torrent
     */
    private $hashString = '';

    /**
     * @var string The torrent name
     */
    private $name = '';

    /**
     * @var int Size of the torrent in bytes
     */
    private $size = 0;

    /**
     * @var int Torrent status must be in the $statuses array
     */
    private $status = '';

    /**
     * @var string A string containing an error message
     */
    private $errorString = 'Unknown';

    /**
     * @var int Download speed in bytes
     */
    private $downloadSpeed = 0;

    /**
     * @var int Upload speed in bytes
     */
    private $uploadSpeed = 0;

    /**
     * @var float Percentage download completion
     */
    private $percentDone = 0.0;

    /**
     * @var int Number of bytes downloaded
     */
    private $bytesDownloaded = 0;

    /**
     * @var int Number of bytes uploaded
     */
    private $bytesUploaded = 0;

    /**
     * @var float Seeding ratio
     */
    private $seedRatio = 0.0;

    /**
     * @var File[] A list of files within the torrent
     */
    private $files = array();

    /**
     * @var int Estimated number of seconds left until torrent is either downloaded / finished seeding
     */
    private $eta;

    /**
     * @constructor
     *
     * @param string $hashString  Uniquely identifiable hash string
     * @param string $name        The name of the torrent
     * @param int    $sizeInBytes The s ize of the torrent in bytes
     */
    public function __construct($hashString, $name, $sizeInBytes)
    {
        $this->name = $name;
        $this->hashString = $hashString;
        $this->setSize($sizeInBytes);
    }

    /**
     * Get torrent hash string
     *
     * @return string The torrent hash string
     */
    public function getHashString()
    {
        return $this->hashString;
    }

    /**
     * Get torrent name
     *
     * @return string The torrent name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the torrent size in bytes
     *
     * @param int $bytes The size of the torrent in bytes
     *
     * @throws \InvalidArgumentException When an invalid size given
     */
    public function setSize($bytes)
    {
        if (is_int($bytes) && $bytes > -1)
        {
            $this->size = $bytes;
        }
        else
        {
            throw new \InvalidArgumentException(
                'Invalid torrent size provided. Size should be bigger than "-1" but "%s" given', $bytes
            );
        }
    }

    /**
     * Get torrent size
     *
     * @return int The torrent size in bytes
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Set the torrent status
     *
     * @param string $status The status of the torrent
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Get the status
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set error string
     *
     * @param string $errorString
     */
    public function setErrorString($errorString)
    {
        $this->errorString = $errorString;
    }

    /**
     * Get error string
     *
     * @return string
     */
    public function getErrorString()
    {
        return $this->errorString;
    }

    /**
     * Set download speed in bytes per second
     *
     * @param int $bytesPerSecond
     *
     * @throws \InvalidArgumentException When an integer is not provided as the parameter
     */
    public function setDownloadSpeed($bytesPerSecond)
    {
        if (is_int($bytesPerSecond) && $bytesPerSecond > -1)
        {
            $this->downloadSpeed = $bytesPerSecond;
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                'Invalid torrent download speed provided. Download speed should be non-negative integer, "%s" given.',
                $bytesPerSecond
            ));
        }
    }

    /**
     * Get download speed in bytes per second
     *
     * @return int
     */
    public function getDownloadSpeed()
    {
        return $this->downloadSpeed;
    }

    /**
     * Set upload speed in bytes per second
     *
     * @param int $bytesPerSecond
     *
     * @throws \InvalidArgumentException When an integer is not provided as the parameter
     */
    public function setUploadSpeed($bytesPerSecond)
    {
        if (is_int($bytesPerSecond) && $bytesPerSecond > -1)
        {
            $this->uploadSpeed = $bytesPerSecond;
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                'Invalid torrent upload speed provided. Upload speed should be non-negative integer, "%s" given.',
                $bytesPerSecond
            ));
        }
    }

    /**
     * Get download speed in bytes per second
     *
     * @return int
     */
    public function getUploadSpeed()
    {
        return $this->uploadSpeed;
    }

    /**
     * Set number of bytes downloaded
     *
     * @param int $numBytes
     *
     * @throws \InvalidArgumentException When an integer is not provided as the parameter
     *
     * @note Also sets the completion percentage, status to complete if 100% and eta
     */
    public function setBytesDownloaded($numBytes)
    {
        if (is_int($numBytes) && $numBytes > -1)
        {
            $this->bytesDownloaded = $numBytes;

            $size = $this->size;

            $percentDone = function() use ($size, $numBytes) {

                $size = ($size === 0) ? 1 : $size;

                if ($size === 0 && $numBytes === 0) {
                    return (float)0;
                }

                return (float)number_format(($numBytes / $size) * 100, 2, '.', '');
            };

            $this->percentDone = $percentDone();

            if ($this->percentDone === 100 || $numBytes === $this->size)
            {
                $this->status = self::STATUS_COMPLETE;
            }

            $this->eta = (int)number_format(($this->size / ($this->downloadSpeed === 0 ? 1 : $this->downloadSpeed)) * 100, 2, '.', '');
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                'Invalid torrent bytes downloaded provided. Amount should be non-negative integer, "%s" given.',
                $numBytes
            ));
        }
    }

    /**
     * Get number of bytes downloaded
     *
     * @return int
     */
    public function getBytesDownloaded()
    {
        return $this->bytesDownloaded;
    }

    /**
     * Set number of bytes uploaded
     *
     * @param int $numBytes
     *
     * @throws \InvalidArgumentException
     *
     * @note Also sets the seed ratio percentage
     */
    public function setBytesUploaded($numBytes)
    {
        if (is_int($numBytes) && $numBytes > -1)
        {
            $this->bytesUploaded = $numBytes;

            $downloaded = $this->bytesDownloaded;

            $seedRatio = function() use ($downloaded, $numBytes) {
                $downloaded = ($downloaded === 0) ? 1 : $downloaded;

                if ($downloaded === 0 && $numBytes === 0) {
                    return (float)0;
                }

                return (float)number_format(($numBytes / $downloaded), 2, '.', '');
            };

            $this->seedRatio = $seedRatio();
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                'Invalid torrent bytes uploaded provided. Amount should be non-negative integer, "%s" given.',
                $numBytes
            ));
        }
    }

    /**
     * Get number of bytes uploaded
     *
     * @return int
     */
    public function getBytesUploaded()
    {
        return $this->bytesUploaded;
    }

    /**
     * Add a file to the torrent
     *
     * @param File $file
     */
    public function addFile(File $file)
    {
        if (!in_array($file, $this->files))
        {
            $this->files[] = $file;
        }
    }

    /**
     * Get the files list
     *
     * @return File[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * Get a specific File by name
     *
     * @param string $name
     *
     * @throws FileNotFoundException When the file does not exist with the given name
     *
     * @return File The found file
     */
    public function getFile($name)
    {
        $files = array_values(array_filter($this->files, function($file) use ($name) {
            /** @var File $file */
            return strtolower($file->getName()) === strtolower($name);
        }));

        if (!empty($files))
        {
            return $files[0];
        }
        else
        {
            throw new FileNotFoundException(sprintf(
                'File with name: "%s" not found for torrent: "%s"', $name, $this->name
            ));
        }
    }

    /**
     * Get eta
     *
     * @return int
     */
    public function getEta()
    {
        return $this->eta;
    }

    /**
     * Get whether or not the torrent has finished
     *
     * @return bool
     */
    public function isComplete()
    {
        return (($this->status === self::STATUS_COMPLETE) || ($this->percentDone === 100) || ($this->bytesDownloaded === $this->size));
    }
}