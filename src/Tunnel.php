<?php
/**
 * This file is a part of tunnel project.
 *
 * (c) Andrey Kolchenko <andrey@kolchenko.me>
 */

namespace Tunnel;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class Tunnel
 *
 * @package Tunnel
 * @author Andrey Kolchenko <andrey@kolchenko.me>
 */
class Tunnel
{
    /**
     * Works in parent process.
     */
    const MODE_PARENT = 0;
    /**
     * Works in child process.
     */
    const MODE_CHILD = 1;
    /**
     * @var int
     */
    private $parentPID;
    /**
     * @var int
     */
    private $currentPID;
    /**
     * @var int
     */
    private $opponentPID;
    /**
     * @var resource[]
     */
    private $bridge;
    /**
     * @var EventDispatcherInterface[]
     */
    private $dispatchers = [];
    /**
     * @var resource
     */
    private $queue;
    /**
     * @var int
     */
    private $mode;

    /**
     * Init Tunnel.
     */
    public function __construct()
    {
        $this->parentPID = getmypid();
        $this->bridge = $this->createBridge();
        $this->queue = msg_get_queue(ftok(__FILE__, 'k'));
        pcntl_signal(POLL_MSG, [$this, 'read']);
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     * @param array $events
     */
    public function registerListener(EventDispatcherInterface $dispatcher, array $events)
    {
        $this->checkTunnelStopped();
        foreach ($events as $event) {
            $dispatcher->addListener($event, [$this, 'onEvent']);
        }
        array_push($this->dispatchers, $dispatcher);
    }

    /**
     * @param Event $event
     * @param string $eventName
     * @param EventDispatcherInterface $dispatcher
     *
     * @return void
     */
    public function onEvent(Event $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        if ($this->opponentPID === null) {
            return;
        }
        $event->setDispatcher(new TransportableEventDispatcher());
        $serialized = serialize($event);
        $sendData = pack('S', strlen($eventName)) . $eventName .
            pack('N', strlen($serialized)) . $serialized .
            pack('S', $this->getDispatcherId($dispatcher));
        if (fwrite($this->bridge[$this->mode], $sendData) === false) {
            throw new \RuntimeException('Could not write event to socket.');
        }
        $this->sendPollSignal();
    }

    /**
     * Read data from opponent.
     */
    public function read()
    {
        if ($this->currentPID === null) {
            return;
        }
        while (msg_receive($this->queue, $this->currentPID, $subscriberPID, 128, $opponentPID, false, MSG_IPC_NOWAIT)) {
            if ($this->currentPID !== $subscriberPID) {
                continue;
            }
            if ($this->opponentPID === null) {
                $this->opponentPID = intval($opponentPID);
            } else {
                $this->processEvent($this->bridge[$this->mode]);
            }
        }
    }

    /**
     * Call after pcntl_fork().
     */
    public function gap()
    {
        $this->checkTunnelStopped();
        $this->currentPID = getmypid();
        if ($this->currentPID === $this->parentPID) {
            $this->mode = self::MODE_PARENT;
        } else {
            $this->mode = self::MODE_CHILD;
            $this->opponentPID = $this->parentPID;
            $this->sendPollSignal();
        }
    }

    /**
     * @param resource $stream
     *
     * @throws \RuntimeException
     */
    private function processEvent($stream)
    {
        try {
            list($eventName, $event, $dispatcherId) = $this->receiveMessage($stream);
            if ($event instanceof Event) {
                $dispatcher = $this->dispatchers[$dispatcherId];
                $dispatcher->removeListener($eventName, [$this, 'onEvent']);
                $dispatcher->dispatch($eventName, $event);
                $dispatcher->addListener($eventName, [$this, 'onEvent']);
            }
        } catch (\RuntimeException $exception) {
            if ($exception->getCode() !== 1) {
                throw $exception;
            }
        }
    }

    /**
     * Send signal to opponent to read data.
     */
    private function sendPollSignal()
    {
        msg_send($this->queue, $this->opponentPID, $this->currentPID, false);
        posix_kill($this->opponentPID, POLL_MSG);
    }

    /**
     * @param resource $stream
     *
     * @return array [$eventName, $event, $dispatcherId]
     */
    private function receiveMessage($stream)
    {
        $eventNameLength = unpack('Slen', $this->readFromStream($stream, 2))['len'];
        $eventName = $this->readFromStream($stream, $eventNameLength);

        $dataLength = unpack('Nlen', $this->readFromStream($stream, 4))['len'];
        $data = $this->readFromStream($stream, $dataLength);
        $event = unserialize($data);

        $dispatcherId = unpack('Sid', $this->readFromStream($stream, 2))['id'];

        return [$eventName, $event, $dispatcherId];
    }

    /**
     * @param resource $stream
     * @param int $length
     *
     * @return null|string
     */
    private function readFromStream($stream, $length)
    {
        if (is_resource($stream) === false) {
            throw new \InvalidArgumentException('Stream must be a resource type.');
        }
        if (feof($stream) === true) {
            throw new \RuntimeException('Stream is empty.', 1);
        }
        $data = stream_get_contents($stream, $length);
        if ($data === false) {
            throw new \RuntimeException('Reading from stream failed.', 2);
        }

        return $data;
    }

    /**
     * Throw exception if tunnel is not work.
     *
     * @throws \LogicException
     */
    private function checkTunnelStopped()
    {
        if ($this->mode !== null) {
            throw new \LogicException('Tunnel is already worked.');
        }
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     *
     * @return int
     */
    private function getDispatcherId(EventDispatcherInterface $dispatcher)
    {
        foreach ($this->dispatchers as $index => $registeredDispatcher) {
            if ($dispatcher === $registeredDispatcher) {
                return $index;
            }
        }
        throw new \InvalidArgumentException('Dispatcher not registered.');
    }

    /**
     * Create a new process bridge.
     *
     * @return resource[] [ $parentSocket, $childSocket ]
     */
    private function createBridge()
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        list($parentSocket, $childSocket) = $pair;
        if ($parentSocket === null || $childSocket === null) {
            throw new \RuntimeException(
                sprintf('Could not create a new pair socket: %s', socket_strerror(socket_last_error()))
            );
        }

        return $pair;
    }
}
