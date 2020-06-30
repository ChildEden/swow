--TEST--
swow_socket: tcp
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
                        $client->sendString($client->readString(MAX_LENGTH));
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

$wr = WaitReference::make();
$randoms = getRandomBytesArray(MAX_REQUESTS, MAX_LENGTH);
for ($c = 0; $c < MAX_CONCURRENCY; $c++) {
    Coroutine::run(function () use ($server, $wr, $randoms) {
        $client = new Socket(Socket::TYPE_TCP);
        $client->connect($server->getSockAddress(), $server->getSockPort());
        for ($n = 0; $n < MAX_REQUESTS; $n++) {
            $client->sendString($randoms[$n]);
        }
        for ($n = 0; $n < MAX_REQUESTS; $n++) {
            $packet = $client->readString(MAX_LENGTH);
            Assert::same($packet, $randoms[$n]);
        }
        $client->close();
    });
}
WaitReference::wait($wr);
$server->close();

echo 'Done' . PHP_LF;

?>
--EXPECT--
Done
