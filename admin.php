<?php
session_start();
set_time_limit(900);

// Údaje z Docker environment (nastaveno v compose.yml)
$apiKey = getenv('OPENAI_API_KEY');
$baseUrl = getenv('OPENAI_BASE_URL');
$model = getenv('AI_MODEL');
$chromaUrl = "http://db:8000/api/v1";

// --- POMOCNÉ FUNKCE PRO CHROMADB ---
function chromaRequest($method, $path, $data = null) {
    global $chromaUrl;
    $ch = curl_init($chromaUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Abychom nečekali věčně
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    }
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Pokud se nepodaří vůbec spojit s kontejnerem
    if ($res === false) {
        die("<div style='background:#e21b3c; color:white; padding:20px; font-family:sans-serif;'>
                <h2>❌ Kontejner databáze neodpovídá</h2>
                <p>cURL Chyba: <b>$curl_error</b></p>
                <p>Zkontroluj v terminálu pomocí <code>docker ps</code>, jestli ChromaDB (kontejner 'db') běží a nespadla kvůli nedostatku RAM.</p>
             </div>");
    }

    $decoded = json_decode($res, true);
    // Pokud odpověď není JSON (např. HTML chyba od serveru)
    if ($decoded === null) {
        return ["api_error" => true, "http_code" => $http_code, "raw" => $res];
    }
    return $decoded;
}

// Získání nebo vytvoření kolekce
function getOrCreateCollection($name) {
    $all = chromaRequest("GET", "/collections");

    // Detekce, pokud databáze vrátila nečekaný formát nebo chybu
    if (isset($all['api_error']) || (is_array($all) && isset($all['error']))) {
        die("<div style='background:#ff9800; color:black; padding:20px; font-family:sans-serif;'>
                <h2>⚠️ ChromaDB vrátila chybu</h2>
                <pre>" . print_r($all, true) . "</pre>
             </div>");
    }

    // Prohledáme existující kolekce (bezpečně)
    if (is_array($all)) {
        foreach ($all as $col) {
            if (is_array($col) && isset($col['name']) && $col['name'] == $name) {
                return $col['id'];
            }
        }
    }

    // Pokud neexistuje, vytvoříme ji
    $new = chromaRequest("POST", "/collections", ["name" => $name]);
    return $new['id'] ?? null;
}

$playerColId = getOrCreateCollection("players");
$questionColId = getOrCreateCollection("questions");
$stateColId = getOrCreateCollection("game_state");

// --- ZPRACOVÁNÍ AKCÍ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reset') {
        chromaRequest("DELETE", "/collections/players");
        chromaRequest("DELETE", "/collections/questions");
        chromaRequest("DELETE", "/collections/game_state");
        $_SESSION['flash_msg'] = "🧹 ChromaDB vyčištěna!";
    } 
    elseif ($_POST['action'] === 'generate') {
        $topic = $_POST['topic'] ?? 'Linux';
        $prompt = "Vytvoř 3 testové otázky na téma: '$topic'. Odpověz POUZE JSONem: [{\"question\":\"...\",\"A\":\"...\",\"B\":\"...\",\"C\":\"...\",\"D\":\"...\",\"correct\":\"A\"}]";

        $postData = [
            "model" => $model,
            "messages" => [["role" => "user", "content" => $prompt]],
            "temperature" => 0.0
        ];

        $ch = curl_init($baseUrl . "/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Authorization: Bearer " . $apiKey]);
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        
        $ai_text = $result['choices'][0]['message']['content'] ?? '';
        // Extrakce JSONu
        preg_match('/\[.*\]/s', $ai_text, $matches);
        $questions = json_decode($matches[0] ?? '', true);

        if ($questions) {
            foreach ($questions as $index => $q) {
                $id = "q_" . time() . "_" . $index;
                chromaRequest("POST", "/collections/$questionColId/upsert", [
                    "ids" => [$id],
                    "metadatas" => [$q],
                    "documents" => ["question_text"]
                ]);
            }
            $_SESSION['flash_msg'] = "🤖 AI vygenerovala " . count($questions) . " otázek!";
        }
    }
    header("Location: admin.php"); exit;
}

// Načtení statistik pro zobrazení
$players = chromaRequest("POST", "/collections/$playerColId/get", ["limit" => 100]);
$qCount = chromaRequest("POST", "/collections/$questionColId/get", ["limit" => 100]);
$playersCount = count($players['ids'] ?? []);
$totalQuestions = count($qCount['ids'] ?? []);
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>ChromaDB Admin Panel</title>
    <style>
        body { font-family: sans-serif; background: #1a1a1a; color: white; text-align: center; padding: 20px; }
        .card { background: #2a2a2a; padding: 30px; border-radius: 15px; max-width: 800px; margin: 0 auto; }
        .btn { padding: 15px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 18px; margin: 10px; color: white; }
        .btn-ai { background: #6f42c1; }
        .btn-reset { background: #e21b3c; }
        input { padding: 10px; width: 80%; margin-bottom: 10px; border-radius: 5px; border: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Admin Panel (Docker + ChromaDB)</h1>
        <p>Hráčů v databázi: <?php echo $playersCount; ?></p>
        <p>Otázek připraveno: <?php echo $totalQuestions; ?></p>

        <form method="POST">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="btn btn-reset">🧹 Tvrdý reset ChromaDB</button>
        </form>

        <div style="border-top: 1px solid #444; margin-top: 20px; padding-top: 20px;">
            <h3>🤖 Generovat z Gemma 3:27b</h3>
            <form method="POST">
                <input type="hidden" name="action" value="generate">
                <input type="text" name="topic" placeholder="Téma (např. Docker, Sítě...)" required>
                <button type="submit" class="btn btn-ai">✨ Vygenerovat otázky</button>
            </form>
        </div>
    </div>
</body>
</html>