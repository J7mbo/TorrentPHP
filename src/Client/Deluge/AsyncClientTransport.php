<?php

namespace TorrentPHP\Client\Deluge;

use Artax\ClientException as HTTPException,
    TorrentPHP\Client\AsyncClientFactory,
    TorrentPHP\ClientException,
    Alert\LibeventReactor,
    Alert\NativeReactor,
    Artax\AsyncClient,
    Artax\Response,
    Artax\Request;

/**
 * Class AsyncClientTransport
 *
 * @package TorrentPHP\Client\Transmission
 */
class AsyncClientTransport extends ClientTransport
{
    /**
     * @var AsyncClientFactory
     */
    protected $clientFactory;

    /**
     * @constructor
     *
     * @param AsyncClientFactory $clientFactory Artax Async HTTP Client
     * @param Request            $request       Empty Artax Request Object
     * @param ConnectionConfig   $config        Configuration object used to connect over rpc
     */
    public function __construct(AsyncClientFactory $clientFactory, Request $request, ConnectionConfig $config)
    {
        $this->connectionArgs = $config->getArgs();
        $this->clientFactory  = $clientFactory;
        $this->request        = $request;
    }

    /**
     * @inheritdoc
     *
     * @param callable $callable A callable that will be given the async response
     */
    public function getTorrents(array $ids = array(), callable $callable = null)
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

        $callable = ($callable === null) ? function(){} : $callable;

        try
        {
            return $this->performRPCRequest($method, $arguments, $callable);
        }
        catch(HTTPException $e)
        {
            throw new ClientException($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param callable $callable Pass in a callable that gets passed one argument which is the response body
     */
    protected function performRPCRequest($method, array $arguments, callable $callable)
    {
        /** @var AsyncClient $client */
        /** @var LibEventReactor|NativeReactor $reactor */
        list ($reactor, $client) = $this->clientFactory->build();
        $request = clone $this->request;

        /** Callback for response data from client **/
        $onResponse = function(Response $response) use ($reactor, $method, $callable) {

            $isJson = function() use ($response) {
                json_decode($response->getBody());
                return (json_last_error() === JSON_ERROR_NONE);
            };

            if ($response->getStatus() !== 200 || !$isJson())
            {
                $reactor->stop();

                throw new HTTPException(sprintf(
                    'Did not get back a JSON response body, got "%s" instead',
                    $method, print_r($response->getBody(), true)
                ));
            }

            $reactor->stop();

            $callable($response->getBody());
        };

        /** Callback on error for either auth response or response **/
        $onError = function(\Exception $e) use($reactor) {

            $reactor->stop();

            throw new HTTPException("Something went wrong..." . $e->getMessage());
        };

        /** Callback when auth response is returned **/
        $onAuthResponse = function(Response $response, Request $request) use ($reactor, $client, $onResponse, $onError, $method, $arguments) {

            if (!$response->hasHeader('Set-Cookie'))
            {
                $reactor->stop();

                throw new HTTPException("Response from torrent client did not return an Set-Cookie header");
            }

            $response = $response->getHeader('Set-Cookie');

            preg_match_all('#_session_id=(.*?);#', $response[0], $matches);
            $cookie = isset($matches[0][0]) ? $matches[0][0]: '';

            $request = clone $request;
            $request->setMethod('POST');
            $request->setHeader('Cookie', array($cookie));
            $request->setBody(json_encode(array(
                'method' => $method,
                'params' => $arguments,
                'id'     => rand()
            )));

            $client->request($request, $onResponse, $onError);
        };

        /** Let's do this **/
        $reactor->immediately(function() use ($reactor, $client, $request, $onAuthResponse, $onError) {

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

            $client->request($request, $onAuthResponse, $onError);
        });

        $reactor->run();
    }
} 