<?php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\Socket\SocketServer;

class GameServer implements MessageComponentInterface {
    protected $clients;
    protected $players = [];
    protected $currentPlayerIndex = 0;
    protected $correctCombination = [];
    protected $gameStarted = false;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        echo "\n========================================";
        echo "\n   GAME SERVER STARTED ON PORT 8190";
        echo "\n========================================\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $this->players[$conn->resourceId] = [
            'id' => $conn->resourceId,
            'ready' => false,
            'combination' => [],
        ];
        echo "[CONNECT] New player connected. ID: {$conn->resourceId}\n";
        $conn->send(json_encode(['type' => 'connected', 'id' => $conn->resourceId]));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data) return;

        $rid = $from->resourceId;

        if ($data['type'] === 'ready') {
            if (isset($this->players[$rid])) {
                $this->players[$rid]['ready'] = true;
                $readyCount = count(array_filter($this->players, fn($p) => $p['ready']));
                echo "[READY] Player {$rid} is ready. Total ready: {$readyCount}/" . count($this->players) . "\n";

                if ($this->allPlayersReady() && count($this->players) >= 2) {
                    $this->startGame();
                }
            }
        } elseif ($data['type'] === 'swap' && $this->gameStarted) {
            $playerIds = array_keys($this->players);
            $currentPlayerId = $playerIds[$this->currentPlayerIndex];

            if ($rid !== $currentPlayerId) {
                echo "[WARNING] Player {$rid} tried to move out of turn!\n";
                return;
            }

            $comb = &$this->players[$rid]['combination'];
            $i1 = $data['index1']; 
            $i2 = $data['index2'];

            if (isset($comb[$i1]) && isset($comb[$i2])) {
                $oldComb = "[" . implode(",", $comb) . "]";
                $temp = $comb[$i1];
                $comb[$i1] = $comb[$i2];
                $comb[$i2] = $temp;
                $newComb = "[" . implode(",", $comb) . "]";
                
                echo "[MOVE] Player {$rid}: swapped indices {$i1} and {$i2}. \n       Result: {$newComb}\n";

                if ($comb === $this->correctCombination) {
                    echo "\n****************************************";
                    echo "\n   WINNER: Player {$rid} matched the goal!";
                    echo "\n****************************************\n";
                    $this->broadcast(['type' => 'win', 'winner' => $rid]);
                    $this->gameStarted = false;
                    foreach($this->players as &$p) $p['ready'] = false;
                } else {
                    $this->nextTurn();
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "[DISCONNECT] Player {$conn->resourceId} left the game.\n";
        unset($this->players[$conn->resourceId]);
        if (count($this->players) < 2 && $this->gameStarted) {
            echo "[INFO] Game stopped: not enough players.\n";
            $this->gameStarted = false;
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[ERROR] {$e->getMessage()}\n";
        $conn->close();
    }

    private function allPlayersReady() {
        if (empty($this->players)) return false;
        foreach ($this->players as $p) if (!$p['ready']) return false;
        return true;
    }

    private function startGame() {
        echo "\n----------------------------------------";
        echo "\n   STARTING NEW GAME SESSION";
        $this->correctCombination = range(0, 8);
        shuffle($this->correctCombination);
        
        echo "\n   TARGET GOAL: [" . implode(",", $this->correctCombination) . "]";
        echo "\n----------------------------------------\n";
        
        foreach ($this->players as $id => &$p) {
            $c = range(0, 8);
            do { shuffle($c); } while ($c === $this->correctCombination);
            $p['combination'] = $c;
            echo "   Player {$id} starts with: [" . implode(",", $p['combination']) . "]\n";
        }
        
        $this->currentPlayerIndex = 0;
        $this->gameStarted = true;
        
        $this->broadcast([
            'type' => 'start',
            'current_player' => array_keys($this->players)[0],
            'players' => $this->players
        ]);
        echo "   Current turn: Player " . array_keys($this->players)[0] . "\n";
    }

    private function nextTurn() {
        $ids = array_keys($this->players);
        $this->currentPlayerIndex = ($this->currentPlayerIndex + 1) % count($ids);
        $nextId = $ids[$this->currentPlayerIndex];
        $this->broadcast([
            'type' => 'turn',
            'player' => $nextId,
            'players' => $this->players
        ]);
        echo "   Next turn: Player {$nextId}\n";
    }

    private function broadcast($msg) {
        $payload = json_encode($msg);
        foreach ($this->clients as $client) $client->send($payload);
    }
}

$loop = React\EventLoop\Loop::get();
$socket = new SocketServer('127.0.0.1:8190', [], $loop);
$server = new IoServer(new HttpServer(new WsServer(new GameServer())), $socket, $loop);
$server->run();
