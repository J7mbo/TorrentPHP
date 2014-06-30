<?php

namespace TorrentPHP\Client\Transmission;

use Artax\ClientException as HTTPException,
    Alert\LibeventReactor,
    Alert\ReactorFactory,
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
     * @var LibeventReactor|NativeReactor
     */
    protected $reactor;

    /**
     * @constructor
     *
     * @param AsyncClient                  $client  Artax Async HTTP Client
     * @param Request                      $request Empty Request object
     * @param ReactorFactory               $reactor Factory for building the Alert Reactor
     * @param ConnectionConfig             $config  Configuration object used to connect over rpc
     */
    public function __construct(AsyncClient $client, Request $request, ReactorFactory $reactor, ConnectionConfig $config)
    {
        $this->connectionArgs = $config->getArgs();
        $this->reactor        = $reactor->select();
        $this->request        = $request;
        $this->client         = $client;
    }

    /**
     * {@inheritdoc}
     *
     * @todo Something to do with a callable being passed in here??
     */
    public function performRpcRequest($method, array $arguments)
    {
        $returnFields = array(
            'hashString', 'name', 'sizeWhenDone', 'status', 'rateDownload', 'rateUpload',
            'uploadedEver', 'files', 'errorString'
        );

        $reactor = clone $this->reactor;
        $client  = clone $this->client;
        $request = clone $this->request;

        /** Callback for response data from client **/
        $onResponse = function(Response $response, Request $request) use ($reactor, $method) {

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

            return $response->getBody();
        };

        /** Callback on error for either auth response or response **/
        $onError = function(\Exception $e, Request $request) use($reactor) {
            $reactor->stop();

            throw new HTTPException("Something went wrong..." . $e->getMessage());
        };

        /** Callback when auth response is returned **/
        $onAuthResponse = function(Response $response, Request $request) use ($reactor, $client, $onResponse, $onError, $method, $returnFields, $arguments) {

            if (!$response->hasHeader('X-Transmission-Session-Id'))
            {
                $reactor->stop();

                throw new HTTPException("Response from torrent client did not return an X-Transmission-Session-Id header");
            }

            $sessionId = $request->getHeader('X-Transmission-Session-Id');

            $request = clone $request;
            $request->setMethod('POST');
            $request->setHeader('X-Transmission-Session-Id', $sessionId);
            $request->setBody(json_encode(array(
                'method'    => $method,
                'arguments' => array_merge(
                    array('fields' => $returnFields),
                    $arguments
                )
            )));

            $client->request($request, $onResponse, $onError);
        };

        /** Let's do this **/
        $reactor->immediately(function() use ($reactor, $client, $request, $onAuthResponse, $onError) {

            $request->setUri(sprintf('%s:%s/transmission/rpc', $this->connectionArgs['host'], $this->connectionArgs['port']));
            $request->setMethod('GET');
            $request->setAllHeaders(array(
                'Content-Type'  => 'application/json; charset=utf-8',
                'Authorization' => sprintf('Basic %s', base64_encode(
                    sprintf('%s:%s', $this->connectionArgs['username'], $this->connectionArgs['password'])
                ))
            ));

            $client->request($request, $onAuthResponse, $onError);
        });
    }
} 