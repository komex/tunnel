<?php
/**
 * This file is a part of tunnel project.
 *
 * (c) Andrey Kolchenko <andrey@kolchenko.me>
 */

namespace Tunnel\Kernel;

/**
 * Class AbstractKernel
 *
 * @package Tunnel\Kernel
 * @author Andrey Kolchenko <andrey@kolchenko.me>
 */
class AbstractKernel implements KernelInterface
{
    /**
     * @var resource
     */
    protected $handler;

    /**
     * @param resource $handler
     *
     * @return $this
     */
    public function setHandler($handler)
    {
        $this->handler = $handler;
    }
}
