<?php
// Zpracovбnн pшihlбљenн novйho hrбиe do ChromaDB
$name = trim($_POST['name'] ?? '');
if (empty($name)) die("Prazdne jmeno");

$chromaUrl = "http://db:8000/api/v1";

function chromaJoinReq($method, $path, $data = null) {
    global $chromaUrl;
    $ch = curl_init($chromaUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// Najdeme ID kolekce 'players'
$cols = chromaJoinReq("GET", "/collections");
$colId = null;
if (is_array($cols)) {
    foreach ($cols as $c) { 
        if (isset($c['name']) && $c['name'] === 'players') $colId = $c['id']; 
    }
}

if (!$colId) die("Hra neni pripravena (Admin musi udelat reset).");

// Vygenerujeme unikбtnн ID pro hrбиe
$playerId = "p_" . time() . "_" . rand(1000, 9999);

// Vloћнme hrбиe do ChromaDB
$data = [
    "ids" => [$playerId],
    "metadatas" => [["name" => $name, "score" => 0, "streak" => 0, "answered" => 0]],
    "documents" => ["player"]
];

chromaJoinReq("POST", "/collections/$colId/upsert", $data);

// Vrбtнme ID hrбиe do jeho mobilu (JavaScriptu)
echo $playerId;
?>