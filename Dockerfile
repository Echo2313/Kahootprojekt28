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

# ---- ABSOLUTNÍ ŘEŠENÍ PRO READ-ONLY SERVER (ČERVÍ DÍRA) ----
# Smažeme originální zamčené složky a nahradíme je zástupci,
# kteří vše tajně přesměrují do povolené paměti v /tmp.
RUN rm -rf /var/run/apache2 && ln -s /tmp /var/run/apache2
RUN rm -rf /var/log/apache2 && ln -s /tmp /var/log/apache2

# PHP sessions také pošleme do /tmp
RUN echo "session.save_path = '/tmp'" > /usr/local/etc/php/conf.d/session.ini
# -----------------------------------------------------------

# Zkopírování tvých souborů do kontejneru
COPY . /var/www/html/

EXPOSE 80
