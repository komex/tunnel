<?php
/**
 * This file is a part of tunnel project.
 *
 * (c) Andrey Kolchenko <andrey@kolchenko.me>
 */

namespace Tunnel\Kernel;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class AbstractKernel
 *
 * @package Tunnel\Kernel
 * @author Andrey Kolchenko <andrey@kolchenko.me>
 */
abstract class AbstractKernel implements KernelInterface
{
    /**
     * @var resource
     */
    protected $handler;
    /**
     * @var EventDispatcherInterface[]
     */
    protected $dispatchers;

    /**
     * @param resource $handler
     *
     * @return $this
     */
    public function setHandler($handler)
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * @param EventDispatcherInterface[] $dispatchers
     *
     * @return $this
     */
    public function setDispatchers(array $dispatchers)
    {
        $this->dispatchers = $dispatchers;

        return $this;
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     *
     * @return int
     */
    protected function getDispatcherId(EventDispatcherInterface $dispatcher)
    {
        foreach ($this->dispatchers as $index => $registeredDispatcher) {
            if ($dispatcher === $registeredDispatcher) {
                return $index;
            }
        }
        throw new \InvalidArgumentException('Dispatcher not registered.');
    }
}
