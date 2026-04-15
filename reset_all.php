<?php
$db_file = 'database.sqlite';
if (file_exists($db_file)) { unlink($db_file); } // Smaže starou databázi i s hráči

$db = new PDO('sqlite:'.$db_file);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Tabulka pro stav hry (přidali jsme 'start_time' pro automatickou sekvenci)
$db->exec("CREATE TABLE game_state (
    id INTEGER PRIMARY KEY,
    status TEXT, -- 'waiting' nebo 'active'
    current_question_id INTEGER,
    start_time INTEGER
)");

// 2. Tabulka pro hráče
$db->exec("CREATE TABLE players (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE,
    score INTEGER DEFAULT 0
)");

// 3. Tabulka pro otázky
$db->exec("CREATE TABLE questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    question_text TEXT,
    option_a TEXT, option_b TEXT, option_c TEXT, option_d TEXT,
    correct_option TEXT
)");

// Vložíme základní stav
$db->exec("INSERT INTO game_state (id, status, current_question_id, start_time) VALUES (1, 'waiting', 0, 0)");

echo "Systém byl kompletně vyčištěn a tabulky vytvořeny! Teď jdi na generate_quiz.php.";
?>