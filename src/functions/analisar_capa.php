<?php
$imagemBase64 = $_POST['imagem'] ?? '';
$mimeType     = $_POST['mime']   ?? 'image/jpeg';

$apiKey = getenv('GEMINI_API_KEY') ?: 'AQ.Ab8RN6L7tg7VkX6v8b5roSRmLVkQL7ncAe9S6YEdtN7dxM9cxg';

// Gemini 2.5 Flash (gratuito)
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-04-17:generateContent?key={$apiKey}";

$prompt = "Analise a capa deste livro e retorne SOMENTE um JSON com: nome, autor, editora, ano, faixaEtaria, confianca.";

$payload = [
    'contents' => [[
        'parts' => [
            ['inline_data' => ['mime_type' => $mimeType, 'data' => $imagemBase64]],
            ['text' => $prompt]
        ]
    ]]
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);

$resposta = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

header('Content-Type: text/plain; charset=utf-8');
echo "=== HTTP CODE ===\n$httpCode\n\n";
echo "=== CURL ERROR ===\n" . ($curlErr ?: '(nenhum)') . "\n\n";
echo "=== RESPOSTA BRUTA DO GEMINI ===\n$resposta\n";
exit;