<?php
$json = json_decode(file_get_contents('php://input'), true);
$name = trim($_POST['nickname'] ?? $_POST['name'] ?? $json['nickname'] ?? '');

if (empty($name)) {
    echo json_encode(["error" => "Prazdne jmeno."]);
    exit;
}

$chromaUrl = "http://127.0.0.1:8000/api/v1";

function chromaJoinReq($method, $path, $data = null) {
    global $chromaUrl;
    $ch = curl_init($chromaUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Nečekáme dlouho
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    }
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    // Pokud selže samotné spojení (DB neběží, spadla, špatný port)
    if ($res === false) return ["api_error" => "Spojení selhalo: " . $err];
    
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

// 3. Výpis PŘESNÉ CHYBY, pokud nemáme ID kolekce
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
