<?php
header('Content-Type: application/json');
$chromaUrl = "http://db:8000/api/v1";

function chromaGet($collectionName) {
    global $chromaUrl;
    // Zjistíme ID kolekce
    $all = json_decode(file_get_contents($chromaUrl . "/collections"), true);
    $colId = null;
    foreach ($all as $col) if ($col['name'] == $collectionName) $colId = $col['id'];
    if (!$colId) return [];

    // Stáhneme data
    $ch = curl_init($chromaUrl . "/collections/$colId/get");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["limit" => 100]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    $res = curl_exec($ch);
    $data = json_decode($res, true);
    return $data['metadatas'] ?? [];
}

$players = chromaGet("players");

// Seřazení hráčů podle skóre (PHP náhrada za SQL ORDER BY)
usort($players, function($a, $b) {
    return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
});

// Omezení na top 5
$leaderboard = array_slice($players, 0, 5);

echo json_encode([
    "phase" => "leaderboard", // Zjednodušeno pro test
    "leaderboard" => $leaderboard
]);