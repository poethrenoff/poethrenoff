<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_work_index', condition: "request.server.get('APP_SITE_CONTEXT') == 'work'")]
    public function workIndex(): Response
    {
        return $this->render('default/work.html.twig');
    }
}
