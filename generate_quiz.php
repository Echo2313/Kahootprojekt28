<?php
$db = new PDO('sqlite:database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$topic = "Operační systém Linux - základní příkazy pro práci se soubory a adresáři v terminálu";

// Zjednodušený prompt
$prompt = "Vygeneruj 3 testové otázky v češtině na téma: '$topic'. " .
          "Odpověz POUZE jako JSON pole objektů, bez jakéhokoliv dalšího textu okolo. " .
          "Zde je přesný vzor, který musíš dodržet:\n" .
          '[{"question": "Tvůj text otázky?", "A": "První", "B": "Druhá", "C": "Třetí", "D": "Čtvrtá", "correct": "A"}]';

$data = [
    "model" => "gemma",
    "prompt" => $prompt,
    "stream" => false,
    // ODSTRANĚNO: "format" => "json" - tohle u Gemmy dělalo ten problém
    "options" => [
        "temperature" => 0.0 // Úplná nula znamená, že si AI nesmí vůbec vymýšlet
    ]
];

$ch = curl_init('http://localhost:11434/api/generate');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
$ai_text = $result['response'] ?? '';

// Drastické čištění od Markdownu (```json ... ```)
$clean_text = trim($ai_text);
$clean_text = preg_replace('/^```json\s*/i', '', $clean_text);
$clean_text = preg_replace('/^```\s*/', '', $clean_text);
$clean_text = preg_replace('/```$/', '', $clean_text);
$clean_text = trim($clean_text);

$questions = json_decode($clean_text, true);

// Pokud AI uspěla
if (is_array($questions) && count($questions) > 0 && isset($questions[0]['question'])) {
    $stmt = $db->prepare("INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($questions as $q) {
        $stmt->execute([
            $q['question'], 
            $q['A'] ?? '-', $q['B'] ?? '-', $q['C'] ?? '-', $q['D'] ?? '-', 
            $q['correct'] ?? 'A'
        ]);
    }
    echo "<h2 style='color: green;'>Úspěch! AI konečně vygenerovala " . count($questions) . " otázek.</h2>";
    echo "<p>Běž na <strong><a href='admin.php'>admin.php</a></strong>.</p>";
} 
// Pokud AI zase selhala, vložíme záchranná data, ať můžeš hrát
else {
    echo "<h2 style='color: orange;'>AI zase nevrátila čistý JSON, ale vložil jsem ti tam záchranné otázky!</h2>";
    echo "<p>Nemusíš čekat, běž rovnou na <strong>