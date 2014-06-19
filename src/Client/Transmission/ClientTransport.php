<?php

namespace TorrentPHP\Client\Transmission;

use TorrentPHP\ClientTransport as ClientTransportInterface,
    Artax\ClientException as HTTPException,
    TorrentPHP\ClientException,
    TorrentPHP\Torrent,
    Artax\Response,
    Artax\Request,
    Artax\Client;

/**
 * Class ClientTransport
 *
 * @package TorrentPHP\Client\Transmission
 *
 * @see <https://trac.transmissionbt.com/browser/trunk/extras/rpc-spec.txt>
 */
class ClientTransport implements ClientTransportInterface
{
    /**
     * RPC Method to call to add a torrent
     */
    const METHOD_ADD = 'torrent-add';

    /**
     * RPC Method to call to get torrent data
     */
    const METHOD_GET = 'torrent-get';

    /**
     * RPC Method to call to delete a torrent and it's data
     */
    const METHOD_DELETE = 'torrent-remove';

    /**
     * RPC Method to call to start a stopped / paused torrent
     */
    const METHOD_START = 'torrent-start';

    /**
     * RPC Method call to stop / pause a started torrent
     */
    const METHOD_PAUSE = 'torrent-stop';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var array Connection arguments
     */
    private $connectionArgs;

    /**
     * @constructor
     *
     * @param Client                       $client  Artax HTTP Client
     * @param Request                      $request Empty Request object
     * @param ConnectionConfig             $config  Configuration object used to connect over rpc
     */
    public function __construct(Client $client, Request $request, ConnectionConfig $config)
    {
        $this->connectionArgs = $config->getArgs();
        $this->request = $request;
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function getTorrents($id = null)
    {
        $method = self::METHOD_GET;
        $arguments = ((is_null($id)) ? array() : array('ids' => array($id)));

        try
        {
            return $this->performRPCRequest($method, $arguments)->getBody();
        }
        catch(HTTPException $e)
        {
            throw new ClientException($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function addTorrent($path)
    {
        $method = self::METHOD_ADD;
        $arguments = array('filename' => $path);

        try
        {
            return $this->performRPCRequest($method, $arguments)->getBody();
        }
        catch(HTTPException $e)
        {
            throw new ClientException($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function startTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $method = self::METHOD_START;

        if (!(is_null($torrent && is_null($torrentId))))
        {
            $arguments = array_merge($this->connectionArgs, array(
                'ids' => (!is_null($torrent) ? $torrent->getHashString() : $torrentId)
            ));

            try
            {
                return $this->performRPCRequest($method, $arguments)->getBody();
            }
            catch(HTTPException $e)
            {
                throw new ClientException($e->getMessage());
            }
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                'Method: "%s" expected at least a Torrent object or torrent id parameter provided, none given', $method
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pauseTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $method = self::METHOD_PAUSE;

        if (!(is_null($torrent && is_null($torrentId))))
        {
            $arguments = array_merge($this->connectionArgs, array(
                'ids' => (!is_null($torrent) ? $torrent->getHashString() : $torrentId)
            ));

            try
            {
                return $this->performRPCRequest($method, $arguments)->getBody();
            }
            catch(HTTPException $e)
            {
                throw new ClientException($e->getMessage());
            }
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                'Method: "%s" expected at least a Torrent object or torrent id parameter provided, none given', $method
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $method = self::METHOD_DELETE;

        if (!(is_null($torrent && is_null($torrentId))))
        {
            $arguments = array_merge($this->connectionArgs, array(
                'ids' => (!is_null($torrent) ? $torrent->getHashString() : $torrentId),
                'delete-local-data' => true
            ));

            try
            {
                return $this->performRPCRequest($method, $arguments)->getBody();
            }
            catch(HTTPException $e)
            {
                throw new ClientException($e->getMessage());
            }
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                'Method: "%s" expected at least a Torrent object or torrent id parameter provided, none given', $method
            ));
        }
    }

    /**
     * Helper method to facilitate json rpc requests using the Artax client
     *
     * @param string $method    The rpc method to call
     * @param array  $arguments Associative array of rpc method arguments to send in the header (not auth arguments)
     *
     * @throws HTTPException When something goes wrong with the HTTP call
     *
     * @return Response The HTTP response containing headers / body ready for validation / parsing
     */
    protected function performRPCRequest($method, array $arguments)
    {
        $returnFields = array(
            'hashString', 'name', 'sizeWhenDone', 'status', 'rateDownload', 'rateUpload',
            'uploadedEver', 'files', 'errorString'
        );

        /** If we don't clone, the connection times out when a second request is made straight after a previous one **/
        /** @see TransmissionClientAdapter::addTorrent() | for an example of two calls being made consecutively */
        $client = clone $this->client;
        $request = clone $this->request;

        $request->setUri(sprintf('%s:%s/transmission/rpc', $this->connectionArgs['host'], $this->connectionArgs['port']));
        $request->setMethod('GET');
        $request->setAllHeaders(array(
            'Content-Type'  => 'application/json; charset=utf-8',
            'Authorization' => sprintf('Basic %s', base64_encode(
                sprintf('%s:%s', $this->connectionArgs['username'], $this->connectionArgs['password'])
            ))
        ));

        $response = $client->request($request);

        if ($response->hasHeader('X-Transmission-Session-Id'))
        {
            $sessionId = $client->request($request)->getHeader('X-Transmission-Session-Id');

            $request->setMethod('POST');
            $request->setHeader('X-Transmission-Session-Id', $sessionId);
            $request->setBody(json_encode(array(
                'method'    => $method,
                'arguments' => array_merge(
                    array('fields' => $returnFields),
                    $arguments
                )
            )));

            $response = $client->request($request);

            if ($response->getStatus() === 200)
            {
                $body = $response->getBody();

                $isJson = function() use ($body) {
                    json_decode($body);
                    return (json_last_error() === JSON_ERROR_NONE);
                };

                if ($isJson())
                {
                    return $response;
                }
                else
                {
                    throw new HTTPException(sprintf(
                        '"%s" did not get back a JSON response body, got "%s" instead',
                        $method, print_r($response->getBody(), true)
                    ));
                }
            }
            else
            {
                throw new HTTPException(sprintf(
                    '"%s" expected 200 response, got "%s" instead, reason: "%s"',
                    $method, $response->getStatus(), $response->getReason()
                ));
            }
        }
        else
        {
            throw new HTTPException("Response from torrent client did not return an X-Transmission-Session-Id header");
        }
    }
}