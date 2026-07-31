<?php

namespace App\EventListener;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Process\Process;

class CacheClearListener implements EventSubscriberInterface
{
    /** @var list<string> */
    private array $contexts = ['www', 'blog', 'work'];
    private bool $commandHandled = false;

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand', 10],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', 10],
        ];
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
        $application = $command->getApplication();
        if (!$application instanceof Application) {
            return;
        }
        $env = $application->getKernel()->getEnvironment();

        $output->writeln('<info>Clearing cache for all site contexts (www, blog, work)...</info>');

        foreach ($this->contexts as $context) {
            $output->writeln("  -> Context: <comment>$context</comment>");

            $commandLine = [PHP_BINARY, 'bin/console', 'cache:clear', '--env=' . $env];

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

        $this->commandHandled = true;
        $event->disableCommand();
        $event->stopPropagation();
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if ($this->commandHandled && $event->getCommand()?->getName() === 'cache:clear') {
            $event->setExitCode(0);
        }

        $this->commandHandled = false;
    }
}
