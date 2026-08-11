<?php

namespace App\Controller;

use App\Service\TelegramService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class BotController extends AbstractController
{
    public function __construct(
        private TelegramService $telegramService,
        #[Autowire(env: 'TELEGRAM_BOT_SECRET')] private string $botSecret,
    ) {
    }

    #[Route('/bot', name: 'telegram_gateway', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        $secret = $request->headers->get('X-Bot-Secret');
        if (!is_string($secret) || !hash_equals($this->botSecret, $secret)) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        $data = json_decode((string) $request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Bad request'], 400);
        }

        $replies = $this->telegramService->computeReplies($data);

        return new JsonResponse(['replies' => $replies]);
    }
}
