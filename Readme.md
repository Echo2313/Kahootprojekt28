Kahoot s AI generátorem

Školní projekt co využívá model ollamy gemma3:27b pro generování otázek

funkce:

V admin panelu do promptu napíšeme požadavky na otázky
PHP aplikace pošle dotaz na ollamu
Ollama pošle zpět čistý JSON soubor
Aplikace si vezme soubor rozebere ho otázky si uloží přímo do databáze chromaDB
A potom můžem hrát kahoot

Použité technologie
Backend: PHP
Frontend: HTML
Databáze: Chromadb
Prostředí: Docker
AI: LLM gemma