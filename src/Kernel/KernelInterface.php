<?php
/**
 * This file is a part of tunnel project.
 *
 * (c) Andrey Kolchenko <andrey@kolchenko.me>
 */

namespace Tunnel\Kernel;


/**
 * Interface KernelInterface
 *
 * @package Tunnel\Kernel
 * @author Andrey Kolchenko <andrey@kolchenko.me>
 */
interface KernelInterface extends EventHandlerInterface
{
    /**
     * @param resource $handler
     *
     * @return $this
     */
    public function setHandler($handler);
}
