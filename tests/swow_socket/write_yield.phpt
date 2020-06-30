--TEST--
swow_socket: write yield
--SKIPIF--
<?php
require __DIR__ . '/../include/skipif.php';
?>
--FILE--
<?php
require __DIR__ . '/../include/bootstrap.php';

use Swow\Coroutine;
use Swow\Socket;
use Swow\Sync\WaitReference;
use const Swow\ECANCELED;
use const Swow\ECONNRESET;

$server = new Socket(Socket::TYPE_TCP);
Coroutine::run(function () use ($server) {
    $server->bind('127.0.0.1')->listen();
    try {
        while (true) {
            $client = $server->accept();
            Coroutine::run(function () use ($client) {
                try {
                    while (true) {
                        $client->sendString($client->readString(MAX_LENGTH_HIGH));
                    }
                } catch (Socket\Exception $exception) {
                    Assert::same($exception->getCode(), ECONNRESET);
                }
            });
        }
    } catch (Socket\Exception $exception) {
        Assert::same($exception->getCode(), ECANCELED);
    }
});

$client = new Socket(Socket::TYPE_TCP);
$client->connect($server->getSockAddress(), $server->getSockPort());
$randoms = getRandomBytesArray(MAX_REQUESTS_LOW, MAX_LENGTH_HIGH);
for ($n = 0; $n < MAX_REQUESTS_LOW; $n++) {
    $client->sendString($randoms[$n]);
}
for ($n = 0; $n < MAX_REQUESTS_LOW; $n++) {
    $packet = $client->readString(MAX_LENGTH_HIGH);
    Assert::same($packet, $randoms[$n]);
}
$client->close();
$server->close();

echo 'Done' . PHP_LF;

?>
--EXPECT--
Done
