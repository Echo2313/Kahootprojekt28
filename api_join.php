<?php
// Extrémní diagnostika - Hledání ztracené databáze
echo "<h2>1. Pokus o překlad běžných jmen (DNS Lookup)</h2>";
$names_to_try = ['db', 'chroma', 'chromadb', 'database', 'vector', 'host.docker.internal'];
foreach ($names_to_try as $name) {
    $ip = gethostbyname($name);
    if ($ip !== $name) {
        echo "✅ BINGO! Jméno <b>'$name'</b> existuje a má IP adresu: <b style='color:green'>$ip</b><br>";
    } else {
        echo "❌ Jméno '$name' nebylo nalezeno.<br>";
    }
}

echo "<h2>2. Tajné proměnné prostředí Dockeru (Envs)</h2>";
echo "<pre style='background:#222; color:#0f0; padding:10px;'>";
foreach ($_SERVER as $key => $value) {
    // Hledáme cokoliv, co obsahuje slovo DB, CHROMA, PORT, TCP nebo HOST
    if (preg_match('/(DB|CHROMA|PORT|TCP|HOST)/i', $key)) {
        echo "$key = $value\n";
    }
}
echo "</pre>";
die();
?>
