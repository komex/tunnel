<?php
/**
 * This file is a part of tunnel project.
 *
 * (c) Andrey Kolchenko <andrey@kolchenko.me>
 */

namespace Tunnel\Kernel;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ParentKernel
 *
 * @package Tunnel\Kernel
 * @author Andrey Kolchenko <andrey@kolchenko.me>
 */
class ParentKernel extends AbstractKernel
{
    /**
     * @param Event $event
     * @param string $eventName
     * @param EventDispatcherInterface $dispatcher
     *
     * @return void
     */
    public function onEvent(Event $event, $eventName, EventDispatcherInterface $dispatcher)
    {

    }
}
