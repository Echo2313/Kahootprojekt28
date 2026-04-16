<?php
$json = json_decode(file_get_contents('php://input'), true);
$name = trim($_POST['nickname'] ?? $_POST['name'] ?? $json['nickname'] ?? '');

if (empty($name)) {
    echo json_encode(["error" => "Prazdne jmeno."]);
    exit;
}

$chromaUrl = "http://db:8000/api/v1";

// FUNKCE BEZ cURL (Odolná proti chybám školní sítě)
function chromaJoinReq($method, $path, $data = null) {
    global $chromaUrl;
    $options = [
        "http" => [
            "method" => $method,
            "header" => "Content-Type: application/json\r\n",
            "timeout" => 5,
            "ignore_errors" => true
        ]
    ];
    if ($data) {
        $options["http"]["content"] = json_encode($data);
    }
    
    $context = stream_context_create($options);
    $res = @file_get_contents($chromaUrl . $path, false, $context);
    
    if ($res === false) {
        $error = error_get_last();
        return ["api_error" => "Stream selhal: " . ($error['message'] ?? 'Neznámá chyba sítě')];
    }
    
    return json_decode($res, true) ?? ["api_error" => "Chybný formát od DB: " . $res];
}

// 1. Zjistíme kolekce
$cols = chromaJoinReq("GET", "/collections");
$colId = null;
if (is_array($cols) && !isset($cols['api_error'])) {
    foreach ($cols as $c) { 
        if (isset($c['name']) && $c['name'] === 'players') $colId = $c['id']; 
    }
}

// 2. Vytvoříme kolekci, pokud není
$newCol = [];
if (!$colId && !isset($cols['api_error'])) {
    $newCol = chromaJoinReq("POST", "/collections", ["name" => "players"]);
    $colId = $newCol['id'] ?? null;
}

// 3. Výpis PŘESNÉ CHYBY
if (!$colId) {
    $errorMsg = $cols['api_error'] ?? $newCol['api_error'] ?? json_encode($newCol);
    echo json_encode(["error" => "Detail chyby DB: " . $errorMsg]);
    exit;
}

// 4. Uložení hráče
$playerId = "p_" . time() . "_" . rand(1000, 9999);
$data = [
    "ids" => [$playerId],
    "metadatas" => [["name" => $name, "score" => 0, "streak" => 0, "answered" => 0]],
    "documents" => ["player"]
];

$upsert = chromaJoinReq("POST", "/collections/$colId/upsert", $data);

if (isset($upsert['api_error'])) {
     echo json_encode(["error" => "Chyba při zápisu hráče: " . $upsert['api_error']]);
     exit;
}

echo json_encode(["player_id" => $playerId]);
?>
