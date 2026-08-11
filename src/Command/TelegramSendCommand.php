<?php

namespace App\Command;

use App\Service\TelegramService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:telegram:send',
    description: 'Sends a text message to a Telegram chat via the AWS bridge',
)]
class TelegramSendCommand extends Command
{
    public function __construct(
        private TelegramService $telegramService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('chatId', InputArgument::REQUIRED, 'Chat or channel id (@username or numeric id)')
            ->addArgument('text', InputArgument::REQUIRED, 'Message text')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $chatId = $input->getArgument('chatId');
        $text = $input->getArgument('text');

        if (!$this->telegramService->publish($chatId, $text)) {
            $io->error('Failed to send message through the bridge');

            return Command::FAILURE;
        }

        $io->success(sprintf('Message sent to %s', $chatId));

        return Command::SUCCESS;
    }
}
