<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AdminChatController extends AbstractController
{
    #[Route('/ask', name: 'chat_ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['message'] ?? '';

        if (empty($question)) {
            return new JsonResponse(['error' => 'Message manquant'], 400);
        }

        // Envoi de la requête à Ollama localement
        $ch = curl_init('http://localhost:11434/api/generate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'llama3',
            'prompt' => $question,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);
        curl_close($ch);

        // Décodage de la réponse JSON d’Ollama
        $lines = explode("\n", trim($response));
        $fullText = '';
        foreach ($lines as $line) {
            $json = json_decode($line, true);
            if (isset($json['response'])) {
                $fullText .= $json['response'];
            }
        }

        return new JsonResponse(['response' => $fullText]);
    }
}
