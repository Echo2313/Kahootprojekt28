<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$playerId = $_POST['player_id'] ?? '';
$answer = trim($_POST['answer'] ?? '');

// Adresa tvého databázového kontejneru (z compose.yml)
$chromaBaseUrl = "http://db:8000/api/v1";

// --- POMOCNÉ FUNKCE PRO CHROMADB ---
function getPlayer($id) {
    global $chromaBaseUrl;
    $url = $chromaBaseUrl . "/collections/players/get";
    $data = ["ids" => [$id]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $res = json_decode($response, true);
    if (!empty($res['metadatas'][0])) return $res['metadatas'][0];
    return null;
}

function updatePlayer($id, $metadata) {
    global $chromaBaseUrl;
    $url = $chromaBaseUrl . "/collections/players/upsert";
    $data = [
        "ids" => [$id],
        "metadatas" => [$metadata],
        "documents" => ["player_data"] // Chroma vyžaduje nějaký text dokumentu
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_exec($ch);
    curl_close($ch);
}
// ------------------------------------

// Zde by normálně byla kontrola herního stavu a správné odpovědi, 
// kterou jsi dříve tahal ze SQLite. Pro ukázku ChromaDB struktury:

$player = getPlayer($playerId);

if (!$player) {
    die("Hrac nenalezen.");
}
if ($player['answered'] == 1) {
    die("Uz jsi odpovedel v tomto kole.");
}

// Simulace správné odpovědi (Zde bys ideálně přes curl vytáhl i správnou odpověď z ChromaDB kolekce 'questions')
$isCorrect = true; // Změň podle reálné logiky

if ($isCorrect) {
    $basePoints = 1000; // Zjednodušeno pro ukázku
    $newStreak = $player['streak'] + 1;
    $totalPoints = $player['score'] + $basePoints + (($newStreak > 1) ? ($newStreak - 1) * 100 : 0);
    
    // Sestavíme nová data hráče
    $updatedData = [
        "name" => $player['name'],
        "score" => $totalPoints,
        "streak" => $newStreak,
        "answered" => 1
    ];
    
    // Uložíme zpět do ChromaDB
    updatePlayer($playerId, $updatedData);
    echo "Spravne";
} else {
    $updatedData = [
        "name" => $player['name'],
        "score" => $player['score'], // Skóre zůstává
        "streak" => 0,               // Streak padá
        "answered" => 1
    ];
    updatePlayer($playerId, $updatedData);
    echo "Spatne";
}
?>