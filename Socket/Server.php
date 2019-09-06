<?php
require_once '../../Vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Socket implements MessageComponentInterface
{
    protected $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $message = json_decode($msg);

        $numRecv = count($this->clients) - 1;

        echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n"
            , $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');

        if($message->clientIp) {
            foreach ($this->clients as $client) {
                $client->send('Connection Established');
            }
        }
        
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                // The sender is not the receiver, send to each client connected
                $client->send($msg);
            }
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    public function onNewObject($msg)
    {
        echo $msg, "\n";
        foreach ($this->clients as $client) {
            $client->send($msg);
        }
    }
}

$loop = React\EventLoop\Factory::create();
$socket = new Socket;

$context = new React\ZMQ\Context($loop);
$pull = $context->getSocket(ZMQ::SOCKET_PULL);
$pull->bind('tcp://127.0.0.1:8384');
$pull->on('message', array($socket, 'onNewObject'));

// Set up our WebSocket server for client
$webSock = new React\Socket\Server('192.168.12.126:8383', $loop);
$webServer = new Ratchet\Server\IoServer(
    new Ratchet\Http\HttpServer(
        new Ratchet\WebSocket\WsServer(
            $socket
        )
    ),
    $webSock
);

$loop->run();
?>