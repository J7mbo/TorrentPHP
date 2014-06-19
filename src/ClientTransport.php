<?php

namespace TorrentPHP;

/**
 * Interface ClientTransport
 *
 * If you want to add support for your own torrent client of choice, you need to create a ClientTransport that sends
 * commands to your client. For transmission and vuze, these are RPC calls over the HTTP protocol, but your client may
 * require command-line calls, for example.
 *
 * The ClientTransport is responsible for the actual data retrieval from your client of choice. The responsibility of
 * adapting the responses to create individual Torrent objects by wrapping this class is the ClientAdapter.
 *
 * @package TorrentPHP
 */
interface ClientTransport
{
    /**
     * Get a list of all torrents from the client
     *
     * @param array $ids Optional array of id / hashStrings to get data for specific torrents
     *
     * @throws ClientException  When the client does not return expected 'success' output
     *
     * @return string A JSON string of data
     */
    public function getTorrents(array $ids = array());

    /**
     * Add a torrent to the client
     *
     * @param string $path The local or remote path to the .torrent file
     *
     * @throws ClientException When the client does not return expected 'success' output
     *
     * @return string A JSON string of response data
     */
    public function addTorrent($path);

    /**
     * Start a torrent
     *
     * @param Torrent $torrent   A Torrent object to start
     * @param int     $torrentId An internal torrent id to start the torrent of
     *
     * @throws \InvalidArgumentException When both input arguments are null
     * @throws ClientException           When the client does not return expected output to say that this action succeeded
     *
     * @return string A JSON string of response data
     */
    public function startTorrent(Torrent $torrent = null, $torrentId = null);

    /**
     * Pause a torrent
     *
     * @param Torrent $torrent   A Torrent object to pause
     * @param int     $torrentId An internal torrent id to pause the torrent of
     *
     * @throws \InvalidArgumentException When both input arguments are null
     * @throws ClientException           When the client does not return expected output to say that this action succeeded
     *
     * @return string A JSON string of response data
     */
    public function pauseTorrent(Torrent $torrent = null, $torrentId = null);

    /**
     * Delete a torrent - be aware this relates to deleting the torrent file and all files associated with it
     *
     * @param Torrent $torrent   A Torrent object to delete
     * @param int     $torrentId An internal torrent id to delete the torrent of
     *
     * @throws \InvalidArgumentException When both input arguments are null
     * @throws ClientException           When the client does not return expected output to say that this action succeeded
     *
     * @return string A JSON string of response data
     */
    public function deleteTorrent(Torrent $torrent = null, $torrentId = null);
} 