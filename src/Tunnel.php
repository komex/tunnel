<?php
/**
 * This file is a part of tunnel project.
 *
 * (c) Andrey Kolchenko <andrey@kolchenko.me>
 */

namespace Tunnel;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tunnel\Kernel\ChildKernel;
use Tunnel\Kernel\EventHandlerInterface;
use Tunnel\Kernel\KernelInterface;
use Tunnel\Kernel\ParentKernel;

/**
 * Class Tunnel
 *
 * @package Tunnel
 * @author Andrey Kolchenko <andrey@kolchenko.me>
 */
class Tunnel implements EventHandlerInterface
{
    /**
     * @var int
     */
    private $parentPID;
    /**
     * @var resource[]
     */
    private $bridge;
    /**
     * @var KernelInterface
     */
    private $kernel;
    /**
     * @var EventDispatcherInterface[]
     */
    private $dispatchers = [];

    /**
     * Init Tunnel.
     */
    public function __construct()
    {
        $this->parentPID = getmypid();
        $this->bridge = $this->createBridge();
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     * @param array $events
     */
    public function registerListener(EventDispatcherInterface $dispatcher, array $events)
    {
        $this->checkTunnelStopped();
        foreach ($events as $event) {
            $priority = 0;
            if (is_array($event)) {
                list($event, $priority) = $event;
            }
            $dispatcher->addListener($event, [$this, 'onEvent'], $priority);
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
        if ($this->kernel !== null) {
            $this->kernel->onEvent($event, $eventName, $dispatcher);
        }
    }

    /**
     * Call after pcntl_fork().
     */
    public function gap()
    {
        $this->checkTunnelStopped();
        $currentPID = getmypid();
        list($parentHandler, $childHandler) = $this->bridge;
        if ($currentPID === $this->parentPID) {
            $kernel = new ParentKernel();
            $kernel->setHandler($parentHandler);
            fclose($childHandler);
        } else {
            $kernel = new ChildKernel();
            $kernel->setHandler($childHandler);
            fclose($parentHandler);
        }
        $kernel->setDispatchers($this->dispatchers);
        $this->kernel = $kernel;
        $this->bridge = null;
        $this->dispatchers = [];
    }


    /**
     * Reset parent PID.
     */
    public function reset()
    {
        $this->checkTunnelStopped();
        $this->parentPID = getmypid();
    }

    /**
     * Throw exception if tunnel is not work.
     *
     * @throws \LogicException
     */
    private function checkTunnelStopped()
    {
        if ($this->bridge === null) {
            throw new \LogicException('Tunnel is already worked.');
        }
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
