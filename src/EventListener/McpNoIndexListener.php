<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * MCP-сервер живёт по пути /mcp без авторизации: в поисковые индексы ему не надо.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class McpNoIndexListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!str_starts_with($event->getRequest()->getPathInfo(), '/mcp')) {
            return;
        }

        $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
    }
}
