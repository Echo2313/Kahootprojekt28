# Použijeme PHP s Apachem
FROM php:8.2-apache

# Instalace potřebných rozšíření
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && docker-php-ext-install curl

# Povolení mod_rewrite
RUN a2enmod rewrite

# ---- KONEČNÁ LIKVIDACE READ-ONLY CHYBY ----
# 1. Přepíšeme proměnné prostředí na konci souboru (přebije vše staré)
RUN echo 'export APACHE_PID_FILE=/tmp/apache2.pid' >> /etc/apache2/envvars && \
    echo 'export APACHE_RUN_DIR=/tmp' >> /etc/apache2/envvars && \
    echo 'export APACHE_LOCK_DIR=/tmp' >> /etc/apache2/envvars && \
    echo 'export APACHE_LOG_DIR=/tmp' >> /etc/apache2/envvars

# 2. Vnutíme to Apache přímo do jeho hlavní konfigurace (zákaz ignorování)
RUN echo 'DefaultRuntimeDir /tmp' >> /etc/apache2/apache2.conf && \
    echo 'PidFile /tmp/apache2.pid' >> /etc/apache2/apache2.conf && \
    echo 'Mutex file:/tmp default' >> /etc/apache2/apache2.conf

# 3. PHP sessions (přihlášení do admina) přesměrujeme do /tmp
RUN echo "session.save_path = '/tmp'" > /usr/local/etc/php/conf.d/session.ini
# --------------------------------------------

# Zkopírování tvých souborů do kontejneru
COPY . /var/www/html/

EXPOSE 80
