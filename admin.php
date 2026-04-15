<?php
session_start();
set_time_limit(900);

// Načtení proměnných z prostředí (Docker / Učitelův server)
$apiKey = getenv('OPENAI_API_KEY');
$baseUrl = getenv('OPENAI_BASE_URL');
$model = getenv('AI_MODEL') ?: 'gemma3:27b';
$chromaUrl = "http://db:8000/api/v1";

// --- POMOCNÉ FUNKCE PRO CHROMADB ---
function chromaReq($method, $path, $data = null) {
    global $chromaUrl;
    $ch = curl_init($chromaUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function getCollectionId($name) {
    $all = chromaReq("GET", "/collections");
    if (is_array($all)) {
        foreach ($all as $col) {
            if (isset($col['name']) && $col['name'] == $name) return $col['id'];
        }
    }
    $new = chromaReq("POST", "/collections", ["name" => $name]);
    return $new['id'] ?? null;
}

$playerColId = getCollectionId("players");
$questionColId = getCollectionId("questions");
$stateColId = getCollectionId("game_state");

// Funkce pro získání a uložení stavu hry
function getGameState() {
    global $stateColId;
    $res = chromaReq("POST", "/collections/$stateColId/get", ["ids" => ["state_1"]]);
    if (!empty($res['metadatas'][0])) return $res['metadatas'][0];
    return ["status" => "waiting", "current_question_id" => "0", "start_time" => 0];
}

function saveGameState($state) {
    global $stateColId;
    chromaReq("POST", "/collections/$stateColId/upsert", [
        "ids" => ["state_1"],
        "metadatas" => [$state],
        "documents" => ["state_data"]
    ]);
}

$state = getGameState();

// --- ZPRACOVÁNÍ AKCÍ TLAČÍTEK ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'reset') {
        chromaReq("DELETE", "/collections/players");
        chromaReq("DELETE", "/collections/questions");
        chromaReq("DELETE", "/collections/game_state");
        $_SESSION['flash_msg'] = "🧹 Tvrdý reset ChromaDB dokončen! Vše je čisté.";
    } 
    elseif ($_POST['action'] === 'generate') {
        $topic = $_POST['topic'] ?? 'Základy IT';
        $prompt = "Vytvoř 3 testové otázky na téma: '$topic'. Odpověz POUZE JSON formátem: [{\"question\":\"text?\",\"A\":\"1\",\"B\":\"2\",\"C\":\"3\",\"D\":\"4\",\"correct\":\"A\"}]";

        $postData = ["model" => $model, "messages" => [["role" => "user", "content" => $prompt]], "temperature" => 0.0];
        $ch = curl_init($baseUrl . "/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Authorization: Bearer " . $apiKey]);
        $res = curl_exec($ch);
        $result = json_decode($res, true);
        
        $ai_text = $result['choices'][0]['message']['content'] ?? '';
        preg_match('/\[.*\]/s', $ai_text, $matches);
        $questions = json_decode($matches[0] ?? '', true);

        if ($questions) {
            foreach ($questions as $index => $q) {
                $id = "q_" . time() . "_" . $index;
                chromaReq("POST", "/collections/$questionColId/upsert", [
                    "ids" => [$id],
                    "metadatas" => [$q],
                    "documents" => ["question_text"]
                ]);
            }
            $_SESSION['flash_msg'] = "🤖 AI úspěšně vygenerovala " . count($questions) . " otázek!";
        } else {
            $_SESSION['flash_msg'] = "⚠️ AI nevrátila správný formát. Zkuste to znovu.";
        }
    }
    elseif ($_POST['action'] === 'start_next') {
        // Jednoduchý posun na další otázku
        $currentId = (int)$state['current_question_id'];
        $nextId = $currentId + 1;
        
        $newState = [
            "status" => "active",
            "current_question_id" => (string)$nextId,
            "start_time" => time()
        ];
        saveGameState($newState);
        $_SESSION['flash_msg'] = "▶ Spuštěna otázka č. " . $nextId;
    }
    elseif ($_POST['action'] === 'skip_to_result') {
        $state['start_time'] = time() - 26;
        saveGameState($state);
    }
    elseif ($_POST['action'] === 'skip_to_leaderboard') {
        $state['start_time'] = time() - 32;
        saveGameState($state);
    }
    
    header("Location: admin.php"); exit;
}

$message = $_SESSION['flash_msg'] ?? '';
unset($_SESSION['flash_msg']);

// Statistiky
$playersData = chromaReq("POST", "/collections/$playerColId/get", ["limit" => 100]);
$questionsData = chromaReq("POST", "/collections/$questionColId/get", ["limit" => 100]);
$playersCount = count($playersData['ids'] ?? []);
$totalQuestions = count($questionsData['ids'] ?? []);

$elapsed = time() - $state['start_time'];
$currentPhase = 'waiting';
if ($state['status'] === 'active') {
    if ($elapsed <= 25) $currentPhase = 'question';
    elseif ($elapsed <= 31) $currentPhase = 'result';
    elseif ($elapsed <= 38) $currentPhase = 'leaderboard';
    else $currentPhase = 'round_ended';
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Prezentační Admin Panel</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f9; margin: 0; padding: 20px; display: flex; justify-content: center; }
        .admin-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 8px 16px rgba(0,0,0,0.1); width: 100%; max-width: 900px; text-align: center; }

        .pres-box { background: #222; color: white; padding: 40px; border-radius: 12px; margin: 20px 0 40px 0; min-height: 250px; box-shadow: inset 0 0 15px rgba(0,0,0,0.5); display: flex; flex-direction: column; justify-content: center; }
        .pres-box h2 { font-size: 32px; margin-top: 0; }
        .pres-answers { display: flex; flex-wrap: wrap; justify-content: space-between; margin-top: 20px; }
        .pres-btn { width: 48%; padding: 25px 10px; margin-bottom: 15px; font-size: 24px; border-radius: 8px; font-weight: bold; color: white !important; text-shadow: 1px 1px 3px rgba(0,0,0,0.8); box-sizing: border-box; text-align: center; }
        .pres-btn-a { background-color: #e21b3c !important; }
        .pres-btn-b { background-color: #1368ce !important; }
        .pres-btn-c { background-color: #d89e00 !important; }
        .pres-btn-d { background-color: #26890c !important; }

        .stats { background: #e9ecef; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; font-size: 18px; margin-bottom: 20px; font-weight: bold;}

        .controls-wrapper { border-top: 2px solid #eee; padding-top: 30px; display: flex; flex-direction: column; gap: 15px; }
        .btn { width: 100%; padding: 18px; font-size: 20px; font-weight: bold; border: none; border-radius: 8px; cursor: pointer; color: white; margin: 0; transition: 0.2s;}
        .btn:hover { opacity: 0.9; }
        .btn-start { background: #28a745; }
        .btn-next { background: #2196F3; }
        .btn-reset { background: #e21b3c; margin-top: 15px; }
        .btn-ai { background: #6f42c1; margin-top: 10px; }

        .ai-section { background: #f3e5f5; border: 2px dashed #ab47bc; padding: 20px; border-radius: 8px; text-align: left; margin-top: 20px;}
        input[type="text"] { width: 100%; padding: 12px; font-size: 16px; margin-bottom: 15px; box-sizing: border-box; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; background: #fff3cd; color: #856404; text-align: left; }
    </style>
</head>
<body>
<div class="admin-card">
    <?php if ($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>

    <div class="stats">
        <span style="color: black;">👥 Hráči: <?php echo $playersCount; ?></span>
        <span style="color: black;">❓ Otázek v DB: <?php echo $totalQuestions; ?></span>
        <span style="color: black;">Fáze: <?php echo $currentPhase; ?></span>
    </div>

    <div id="presentation-box" class="pres-box">
        <h2>Čekáme na spuštění hry...</h2>
        <p style="color:#aaa;">(Stav se aktualizuje za běhu po napojení na status API)</p>
    </div>

    <div class="controls-wrapper">
        <?php if ($state['status'] === 'waiting' || $currentPhase === 'round_ended'): ?>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="start_next">
                <button type="submit" class="btn btn-start">▶ Spustit další otázku</button>
            </form>
        <?php elseif ($currentPhase === 'question'): ?>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="skip_to_result">
                <button type="submit" class="btn btn-next">⏭ Ukončit časovač (Ukázat výsledek)</button>
            </form>
        <?php elseif ($currentPhase === 'result'): ?>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="skip_to_leaderboard">
                <button type="submit" class="btn btn-next">⏭ Ukázat tabulku bodů</button>
            </form>
        <?php elseif ($currentPhase === 'leaderboard'): ?>
            <form method="POST" style="margin:0;">
                <input type="hidden" name="action" value="start_next">
                <button type="submit" class="btn btn-start">▶ Spustit další otázku</button>
            </form>
        <?php endif; ?>

        <form method="POST" onsubmit="return confirm('Opravdu smazat všechny hráče i otázky?');" style="margin:0;">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="btn btn-reset">🧹 Tvrdý reset (Nutné před první hrou)</button>
        </form>
    </div>

    <div class="ai-section">
        <h3 style="margin-top:0; color:#4a148c;">🤖 Generátor otázek (Gemma)</h3>
        <form method="POST" onsubmit="document.getElementById('ai-btn').innerText = '⏳ Generuji (čekej, komunikuji se serverem)...'; document.getElementById('ai-btn').style.opacity = '0.5';">
            <input type="hidden" name="action" value="generate">
            <input type="text" name="topic" placeholder="Zadej téma pro AI (např. Sítě, Linux, Docker)..." required>
            <button type="submit" id="ai-btn" class="btn btn-ai">✨ Vygenerovat otázky k tématu</button>
        </form>
    </div>
</div>
</body>
</html>
