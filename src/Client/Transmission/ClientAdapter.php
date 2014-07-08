<?php

namespace TorrentPHP\Client\Transmission;

use TorrentPHP\ClientAdapter as BaseClientAdapter,
    TorrentPHP\Torrent;

/**
 * Class ClientAdapter
 *
 * {@inheritdoc}
 *
 * @package TorrentPHP\Transmission
 */
class ClientAdapter extends BaseClientAdapter
{
    /**
     * @see ClientTransport::getTorrents()
     */
    public function getTorrents(array $ids = array(), callable $callable = null)
    {
        if ($callable === null)
        {
            $data = $this->transport->getTorrents($ids);

            return $this->convertJsonToTorrentObjects($data);
        }
        else
        {
            $jsonToTorrents = function($jsonResponse) use ($callable) {
                $callable($this->convertJsonToTorrentObjects($jsonResponse));
            };

            $this->transport->getTorrents($ids, $jsonToTorrents);
        }
    }

    /**
     * @see ClientTransport::addTorrent()
     */
    public function addTorrent($path)
    {
        $data = json_decode($this->transport->addTorrent($path));

        $key = 'torrent-added';
        $torrentHash = $data->arguments->$key->hashString;
        $torrents = $this->getTorrents($torrentHash);

        return $torrents[0];
    }

    /**
     * @see ClientTransport::pauseTorrent()
     */
    public function pauseTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $this->transport->pauseTorrent($torrent, $torrentId);

        $torrentHash = (!is_null($torrent)) ? $torrent->getHashString() : $torrentId;

        $torrents = $this->getTorrents($torrentHash);

        return $torrents[0];
    }

    /**
     * @see ClientTransport::startTorrent()
     */
    public function startTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $this->transport->startTorrent($torrent, $torrentId);

        $torrentHash = (!is_null($torrent)) ? $torrent->getHashString() : $torrentId;

        $torrents = $this->getTorrents($torrentHash);

        return $torrents[0];
    }

    /**
     * @see ClientTransport::deleteTorrent()
     */
    public function deleteTorrent(Torrent $torrent = null, $torrentId = null)
    {
        $data = json_decode($this->transport->deleteTorrent($torrent, $torrentId));

        return ($data->result === 'success');
    }

    /**
     * Helper method used by both sync and async calls to convert json data of torrents into actual Torrent objects
     *
     * @param string $data JSON data from the client
     *
     * @return array
     */
    private function convertJsonToTorrentObjects($data)
    {
        $torrents = array();

        $data = json_decode($data);

        foreach ($data->arguments->torrents as $obj)
        {
            $torrent = $this->torrentFactory->build($obj->hashString, $obj->name, $obj->sizeWhenDone);
            $torrent->setDownloadSpeed($obj->rateDownload);
            $torrent->setUploadSpeed($obj->rateUpload);
            $torrent->setErrorString($obj->errorString);

            /** Transmission uses integers for statuses **/
            /** @see <https://trac.transmissionbt.com/browser/branches/2.4x/libtransmission/transmission.h> **/
            switch ($obj->status)
            {
                case 0:
                    $status = Torrent::STATUS_PAUSED;
                    break;
                case 1:
                    $status = 'Queued to check';
                    break;
                case 2:
                    $status = 'Checking';
                    break;
                case 3:
                    $status = 'Queued to download';
                    break;
                case 4:
                    $status = Torrent::STATUS_DOWNLOADING;
                    break;
                case 5:
                    $status = 'Queued to seed';
                    break;
                case 6:
                    $status = Torrent::STATUS_SEEDING;
                    break;
                default:
                    $status = 'Unknown';
                    break;
            }

            $torrent->setStatus($status);

            /** Adding filesizes together for total size as $obj->downloadedEver is unreliable copmared with size **/
            $bytesDownloaded = 0;

            foreach ($obj->files as $fileData)
            {
                $torrent->addFile($this->fileFactory->build($fileData->name, $fileData->length));

                $bytesDownloaded += $fileData->bytesCompleted;
            }

            $torrent->setBytesDownloaded($bytesDownloaded);
            $torrent->setBytesUploaded($obj->uploadedEver);

            $torrents[] = $torrent;
        }

        return $torrents;
    }
}