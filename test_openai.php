<?php
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use OpenAI;

// Charger les variables du fichier .env
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/.env');

// Récupérer la clé API
$apiKey = $_ENV['OPENAI_API_KEY'] ?? null;

if (!$apiKey) {
    die("❌ Clé API non trouvée. Vérifie ton fichier .env ou .env.local\n");
}

$client = OpenAI::client($apiKey);

try {
    $response = $client->chat()->create([
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'user', 'content' => 'Bonjour, peux-tu me répondre ?'],
        ],
    ]);


    echo "✅ Réponse de l’IA : " . $response->choices[0]->message->content . PHP_EOL;

} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . PHP_EOL;
}
