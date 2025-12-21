<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class PortfolioPageController extends AbstractController
{
    #[Route('/portfolio', name: 'portfolio_page', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('portfolio.html.twig');
    }
}
