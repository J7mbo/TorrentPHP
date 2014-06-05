<?php

namespace TorrentPHP;

/**
 * Class ClientAdapter
 *
 * This class decorates (wraps) the ClientTransport object. It is used to turn any json into valid Torrent objects.
 *
 * Methods defined in here can override those in ClientTransport and modify the output (for example - into Torrents).
 * Methods not defined in here are forwarded to the ClientTransport, if they exist.
 *
 * To write your own client adapter, create a class that extends this class, and implement your own client-specific
 * decoration methods that override your client transport methods.
 *
 * @see <http://stackoverflow.com/a/15342432/736809>
 *
 * @package TorrentPHP\Client
 */
abstract class ClientAdapter
{
    /**
     * @var ClientTransport
     */
    protected $transport;

    /**
     * @var TorrentFactory
     */
    protected $torrentFactory;

    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @constructor
     *
     * @param ClientTransport $transport
     * @param TorrentFactory  $torrentFactory
     * @param FileFactory     $fileFactory
     */
    public function __construct(ClientTransport $transport, TorrentFactory $torrentFactory, FileFactory $fileFactory)
    {
        $this->transport = $transport;
        $this->torrentFactory = $torrentFactory;
        $this->fileFactory = $fileFactory;
    }

    /**
     * Forwards calls to any methods not defined in this class to the decorated transport object
     *
     * @param string $method The method being called
     * @param array  $args   Optional arguments being passed to the method
     *
     * @throws \Exception When a method is called that doesn't exist in the transport class
     *
     * @return mixed The response from the transport class
     */
    public function __call($method, $args)
    {
        if (method_exists($this->transport, $method))
        {
            return call_user_func_array(array($this->transport, $method), $args);
        }
        else
        {
            throw new \Exception(sprintf('Undefined method: "%s" within transport class: "%s".',
                $method, get_class($this->transport)
            ));
        }
    }
} 