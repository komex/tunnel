<?php
/**
 * This file is a part of tunnel project.
 *
 * (c) Andrey Kolchenko <andrey@kolchenko.me>
 */

namespace Tunnel;

use Tunnel\Kernel\ChildKernel;
use Tunnel\Kernel\KernelInterface;
use Tunnel\Kernel\ParentKernel;

/**
 * Class Tunnel
 *
 * @package Tunnel
 * @author Andrey Kolchenko <andrey@kolchenko.me>
 */
class Tunnel
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
     * Init Tunnel.
     */
    public function __construct()
    {
        $this->parentPID = getmypid();
        $this->bridge = $this->createBridge();
    }

    /**
     * Call after pcntl_fork().
     */
    public function gap()
    {
        $this->checkTunnelStatus();
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
        $this->kernel = $kernel;
        $this->bridge = null;
    }


    /**
     * Reset parent PID.
     */
    public function reset()
    {
        $this->checkTunnelStatus();
        $this->parentPID = getmypid();
    }

    /**
     * Throw exception if tunnel is already in work.
     *
     * @throws \LogicException
     */
    private function checkTunnelStatus()
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
