<?php

namespace TorrentPHP;

/**
 * Class FileNotFoundException
 *
 * This exception is thrown when a user requests a File (by name) from within a Torrent, and the file cannot be found
 *
 * @package TorrentPHP
 */
class FileNotFoundException extends \Exception { }