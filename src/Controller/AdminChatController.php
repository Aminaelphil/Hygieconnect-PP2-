<?php

namespace App\Controller;

use App\Service\ChatAiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminChatController extends AbstractController
{
    #[Route('/admin/chat', name: 'app_admin_chat')]
    public function index(Request $request, ChatAiService $chatAi): Response
    {
        $answer = null;

        if ($request->isMethod('POST')) {
            $question = $request->request->get('question');
            $context = "Tu parles à un administrateur HygieConnect. Donne des infos sur les entreprises clientes.";

            $answer = $chatAi->ask($question, $context);
        }

        return $this->render('admin/chat.html.twig', [
            'answer' => $answer,
        ]);
    }
}
