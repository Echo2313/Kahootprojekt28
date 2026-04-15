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

# ---- AGRESIVNÍ ŘEŠENÍ PRO READ-ONLY SERVER ----
# Apache ignoruje naše ENV proměnné, takže mu ty cesty
# k zamčeným složkám fyzicky přepíšeme přímo v jeho "mozku" (envvars).
RUN sed -i 's|export APACHE_RUN_DIR=.*|export APACHE_RUN_DIR=/tmp|g' /etc/apache2/envvars && \
    sed -i 's|export APACHE_LOCK_DIR=.*|export APACHE_LOCK_DIR=/tmp|g' /etc/apache2/envvars && \
    sed -i 's|export APACHE_LOG_DIR=.*|export APACHE_LOG_DIR=/tmp|g' /etc/apache2/envvars

# Přinutíme PHP ukládat session (přihlášení admina) do /tmp
RUN echo "session.save_path = '/tmp'" > /usr/local/etc/php/conf.d/session.ini
# -----------------------------------------------

# Zkopírování tvých souborů do kontejneru
COPY . /var/www/html/

EXPOSE 80
