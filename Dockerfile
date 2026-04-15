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

# ---- ŘEŠENÍ PRO READ-ONLY SERVER (ŠKOLNÍ LIMITY) ----
# Přinutíme Apache používat povolenou složku /tmp
ENV APACHE_PID_FILE=/tmp/apache2.pid
ENV APACHE_RUN_DIR=/tmp
ENV APACHE_LOCK_DIR=/tmp
# Přinutíme PHP ukládat session (přihlášení admina) do /tmp
RUN echo "session.save_path = '/tmp'" > /usr/local/etc/php/conf.d/session.ini
# -----------------------------------------------------

# Zkopírování tvých souborů do kontejneru
COPY . /var/www/html/

EXPOSE 80
