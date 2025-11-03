<?php

namespace App\Service;

use OpenAI;

class ChatAiService
{
    private $client;

    public function __construct(string $apiKey)
    {
        $this->client = OpenAI::client($apiKey);
    }

    public function ask(string $question, ?string $context = null): string
    {
     try {
        $prompt = $context
            ? "Contexte : $context\n\nQuestion : $question"
            : $question;

        $response = $this->client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Tu es un assistant utile qui connaît tout sur les entreprises clientes de HygieConnect.'],
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        return $response->choices[0]->message->content;
    } catch (\OpenAI\Exceptions\RateLimitException $e) {
        return "⚠️ Le service d'IA est temporairement surchargé. Réessaie dans quelques instants.";
    } catch (\Exception $e) {
        return "❌ Une erreur s'est produite : " . $e->getMessage();
    }
}
}
