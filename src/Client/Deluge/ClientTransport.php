<?php

namespace TorrentPHP\Client\Deluge;

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
 * @package TorrentPHP\Client\Deluge
 *
 * @see <http://deluge-torrent.org/docs/1.2/modules/core/core.html>
 * @see <http://dev.deluge-torrent.org/ticket/2085#comment:4>
 */
class ClientTransport implements ClientTransportInterface
{
    /**
     * RPC Method to call for authentication
     */
    const METHOD_AUTH = 'auth.login';

    /**
     * RPC Method to call to get torrent data for all torrents
     */
    const METHOD_GET_ALL = 'core.get_torrents_status';

    /**
     * RPC Method to call to add a torrent from a url
     */
    const METHOD_ADD = 'core.add_torrent_url';

    /**
     * RPC Method to call to start a torrent
     */
    const METHOD_START = 'core.resume_torrent';

    /**
     * RPC Method to call to pause a torrent
     */
    const METHOD_PAUSE = 'core.pause_torrent';

    /**
     * RPC Method to call to delete a torrent and it's associated data
     */
    const METHOD_DELETE = 'core.remove_torrent';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var array Connection arguments
     */
    protected $connectionArgs;

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
    public function getTorrents(array $ids = array())
    {
        $method = self::METHOD_GET_ALL;

        $arguments = array(
            /** Torrent ID if provided - null returns all torrents **/
            empty($ids) ? null : ['id' => $ids],
            /** Return Keys **/
            array(
                'name', 'state', 'files', 'eta', 'hash', 'download_payload_rate', 'status',
                'upload_payload_rate', 'total_wanted', 'total_uploaded', 'total_done', 'error_code'
            )
        );

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
        $arguments = array(
            /** Torrent Url **/
            $path,
            /** Required array of optional arguments (required and also optional? wtf was the api designer thinking) **/
            array()
        );

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
            $arguments = array(
                /** The torrent to start **/
                array(!is_null($torrent) ? $torrent->getHashString() : $torrentId)
            );

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
            $arguments = array(
                /** The torrent to pause **/
                array(!is_null($torrent) ? $torrent->getHashString() : $torrentId)
            );

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
            $arguments = array(
                /** The torrent to delete **/
                !is_null($torrent) ? $torrent->getHashString() : $torrentId,
                /** Boolean to remove all associated data **/
                true
            );

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
    private function performRPCRequest($method, array $arguments)
    {
        $client = $this->client;
        $request = $this->request;

        $request->setUri(sprintf('%s:%s/json', $this->connectionArgs['host'], $this->connectionArgs['port']));
        $request->setMethod('POST');
        $request->setAllHeaders(array(
            'Content-Type'  => 'application/json; charset=utf-8'
        ));
        $request->setBody(json_encode(array(
            'method' => self::METHOD_AUTH,
            'params' => array(
                $this->connectionArgs['password']
            ),
            'id'     => rand()
        )));

        $response = $client->request($request);

        if ($response->hasHeader('Set-Cookie'))
        {
            $response = $client->request($request)->getHeader('Set-Cookie');

            preg_match_all('#_session_id=(.*?);#', $response[0], $matches);
            $cookie = isset($matches[0][0]) ? $matches[0][0]: '';

            $request->setMethod('POST');
            $request->setHeader('Cookie', array($cookie));
            $request->setBody(json_encode(array(
                'method' => $method,
                'params' => $arguments,
                'id'     => rand()
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
            throw new HTTPException("Response from torrent client did not return a Set-Cookie header");
        }
    }
}