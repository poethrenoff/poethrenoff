<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        $container->setParameter('app.site_context', $_SERVER['APP_SITE_CONTEXT'] ?? 'www');
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment.'/'.($_SERVER['APP_SITE_CONTEXT'] ?? 'www');
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir().'/var/log/'.($_SERVER['APP_SITE_CONTEXT'] ?? 'www');
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
