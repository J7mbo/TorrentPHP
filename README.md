TorrentPHP
===

Provides a simple-to-use object oriented interface for interacting with torrent clients. With this library, you can retrieve Torrent data as `Torrent` objects, and otherwise tell your torrent client to perform actions like `pauseTorrent` and `startTorrent`.

Currently supported clients *(with remote capabilities enabled)*:

- Transmission 
- Deluge

Installation
===

Installation is via [Composer](https://getcomposer.org/). Add the following to your `composer.json` file:

    "require": {
        "j7mbo/torrent-php": "dev-master" 
    }

Don't forget to run `composer install` to create the `vendor/` directory containing all the project's dependencies automatically for you.

Overview
===

This library isn't particularly complex. It performs the following to give you an array of `Torrent` objects, in this order:

**`ClientTransport`**
- **Retrieves the data from your client**. In the case of Transmission and Deluge, this is done via JSON-RPC calls over the HTTP protocol.

**`ClientAdapter`**

- **Turns the data retrieved from the transport into `Torrent` objects**. This object wraps the transport and alters the output using the adapter pattern.

**`Torrent`**

You get out `Torrent` objects for use within your own application.

Usage
===

Obviously, the first thing after running `composer install` is to include the automatically generated autoloader file:

    require_once "/path/to/project" . "/vendor/autoload.php";

Create a `ConnectionConfig` object, with the required connection parameters. These differ depending on the client, so check the docblock above the relevant constructor signature. The following examples are using Transmission.

    use TorrentPHP\Client\Transmission\ConnectionConfig;

    $config = new ConnectionConfig(array(
        'host' => 'localhost',
        'port' => 9091,
        'username' => 'james',
        'password' => 'password'
    ));

Instead of using cURL to make the RPC requests, we're using [Artax](https://github.com/rdlowrey/Artax). We need a new `Client` object and a new `Request` object.

    $client = new Artax\Client;
    $config = new Artax\Request;

Now, to give you JSON, use the `ClientTransport` object:

    use TorrentPHP\Client\Transmission\ClientTransport;

    $transport = new ClientTransport($client, $request, $config);

Here you can run any of the methods defined in the `ClientTransport` interface, like:

    $transport->getTorrents();
    $transport->addTorrent('http://urlToTorrentFile.torrent');

The following methods allow you to pass either a `Torrent` object as the first parameter, or a torrent id (hash) as a second parameter:

    $transport->startTorrent();
    $transport->pauseTorrent();
    $transport->deleteTorrent();

The above methods all return the raw json provided by the client. If you want lovely `Torrent` objects that remain consistent for use around your application, wrap the transport in the relevant `ClientAdapter` before making the exact same call.

    use TorrentPHP\Clent\Trasmission\ClientAdapter,
        TorrentPHP\TorrentFactory,
        TorrentPHP\FileFactory;

    $adapter = new ClientAdapter($transport, new TorrentFactory, new FileFactory);

Then, just call the same methods on the adapter to get lovely `Torrent` and `File` objects back. Simple!

Torrent Object
===

The `Torrent` object is the final entity you will be given containing the properties chosen to be made available to you via your torrent client of choice.

![PHP Torrent Object](http://imgsharer.eu/uploads/979/torrent_1401970300.png "Torrent Object")

All of the above properties are private, and are accessible via getters; e.g. `getHashString()` and `getName()`.

Take a look at the `Torrent` class to see the available methods to get the data you require.

Code Example
===

    require_once __DIR__ . "/vendor/autoload.php";

    use TorrentPHP\Client\Transmission\ConnectionConfig,
        TorrentPHP\Client\Transmission\ClientTransport,
        TorrentPHP\Client\Transmission\ClientAdapter,
        TorrentPHP\TorrentFactory,
        TorrentPHP\FileFactory;

    // Create the HTTP Client Object
    $client = new Artax\Client;

    // Create the HTTP Client Request
    $request = new Artax\Request;

    // Configuration
    $config = new ConnectionConfig(array(
        'host'     => 'localhost', 
        'port'     => 9091, 
        'username' => 'james',
        'password' => 'password'
    ));

    // Create the transport that returns json
    $transport = new ClientTransport($client, $request, $config);

    // Create the adapter that returns Torrent objects
    $adapter = new ClientAdapter($transport, new TorrentFactory, new FileFactory);

    // Add a torrent, and get back a Torrent object
    $torrent = $adapter->addTorrent('http://releases.ubuntu.com/14.04/ubuntu-14.04-server-i386.iso.torrent');

    // Pause the torrent we just added
    $torrent = $adapter->pauseTorrent($torrent);

    // Start the torrent we just added
    $torrent = $adapter->startTorrent($torrent);

    // Delete a torrent by it's hash instead of the object
    $adapter->deleteTorrent(null, $torrent->getHashString());

Torrent Clients
===

**Transmission** requires `transmission-remote-gui` installed with it's config set up to allow remote connections.

**Deluge** requires both `deluged` and `deluge-web` running.

Extension
====

You can add support for your own client by creating a new client directory in `src/Client/<ClientName>`, and adding a `ClientAdapter`, `ClientTransport` and `ConnectionConfig`. They don't have to talk over RPC however as how the implementation handles it is separate from the rest of the application.

Make sure you use the correct namespaces and implement / extend the correct classes. See the `src/Transmission` and `src/Deluge` directories for examples.

If you would like support added for a different torrent client, and don't know how best to go about this yourself, feel free to submit an issue and we can work on it together.
