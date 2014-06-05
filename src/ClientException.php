<?php

namespace TorrentPHP;

/**
 * Class ClientException
 *
 * This exception is thrown when a client does not return expected output in response to a call (like start, stop)
 * because something went wrong - this could include authentication errors, network errors etc
 *
 * @package TorrentPHP
 */
class ClientException extends \Exception { }