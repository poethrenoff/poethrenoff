<?php

namespace App\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Process\Process;

class CacheClearListener implements EventSubscriberInterface
{
    private array $contexts = ['www', 'blog', 'work'];

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand', 10],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', 10],
        ];
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if ($event->getExitCode() === ConsoleCommandEvent::RETURN_CODE_DISABLED && $event->getCommand()?->getName() === 'cache:clear') {
            $event->setExitCode(0);
        }
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if (null === $command || $command->getName() !== 'cache:clear') {
            return;
        }

        // Если контекст уже задан (через переменную окружения), то выполняем обычную очистку для этого контекста.
        if (isset($_SERVER['APP_SITE_CONTEXT']) || isset($_ENV['APP_SITE_CONTEXT'])) {
            return;
        }

        $output = $event->getOutput();
        $input = $event->getInput();
        $env = $command->getApplication()->getKernel()->getEnvironment();

        $output->writeln('<info>Clearing cache for all site contexts (www, blog, work)...</info>');

        foreach ($this->contexts as $context) {
            $output->writeln("  -> Context: <comment>$context</comment>");
            
            // Собираем команду для запуска в подпроцессе
            $commandLine = [PHP_BINARY, 'bin/console', 'cache:clear', '--env=' . $env];
            
            // Пробрасываем важные опции
            if ($input->getOption('no-warmup')) {
                $commandLine[] = '--no-warmup';
            }
            if ($input->getOption('no-optional-warmers')) {
                $commandLine[] = '--no-optional-warmers';
            }

            $process = new Process($commandLine);
            $process->setEnv(['APP_SITE_CONTEXT' => $context]);
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                $output->writeln("<error>Failed to clear cache for context $context</error>");
                $output->writeln($process->getErrorOutput());
            }
        }

        $output->writeln('<info>All site context caches have been cleared.</info>');

        // Отменяем выполнение оригинальной команды для "пустого" контекста,
        // так как мы уже всё очистили для конкретных контекстов.
        $event->disableCommand();
        $event->stopPropagation();
    }
}
