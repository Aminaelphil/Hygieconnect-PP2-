<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaChatService
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function ask(string $question, ?string $context = null): string
    {
        $prompt = $context
            ? "Contexte : $context\n\nQuestion : $question"
            : $question;

        try {
            $response = $this->client->request('POST', 'http://localhost:11434/api/chat', [
                'json' => [
                    'model' => 'llama3',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Tu es un assistant utile et professionnel, spécialisé dans HygieConnect.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ],
            ]);

            $data = $response->toArray();
            return $data['message']['content'] ?? 'Aucune réponse reçue.';
        } catch (\Exception $e) {
            return '❌ Erreur : ' . $e->getMessage();
        }
    }
}
