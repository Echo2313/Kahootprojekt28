# Použijeme čisté PHP (bez tvrdohlavého Apache)
FROM php:8.2-cli

# Instalace curl pro komunikaci s AI a ChromaDB
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && docker-php-ext-install curl

# Zkopírování tvých souborů do kontejneru
COPY . /var/www/html/

# Nastavíme se do správné složky
WORKDIR /var/www/html/

# PHP sessions (přihlášení učitele) nasměrujeme do povolené /tmp složky
RUN echo "session.save_path = '/tmp'" > /usr/local/etc/php/conf.d/session.ini

# Otevřeme port 80
EXPOSE 80

# ZLATÝ TRIK: Zapneme podporu pro více hráčů najednou (až 20 současných spojení)
ENV PHP_CLI_SERVER_WORKERS=20

# Spustíme zabudovaný PHP server, který nepotřebuje ukládat vůbec nic!
CMD ["php", "-S", "0.0.0.0:80"]
