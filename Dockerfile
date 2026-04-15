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

# ---- ULTIMÁTNÍ ŘEŠENÍ PRO READ-ONLY ----
# Kompletně přepíšeme startovací proměnné Apache. 
# Smažeme originál a vytvoříme nový, který zná jen složku /tmp.
RUN echo 'export APACHE_RUN_USER=www-data' > /etc/apache2/envvars && \
    echo 'export APACHE_RUN_GROUP=www-data' >> /etc/apache2/envvars && \
    echo 'export APACHE_PID_FILE=/tmp/apache2.pid' >> /etc/apache2/envvars && \
    echo 'export APACHE_RUN_DIR=/tmp' >> /etc/apache2/envvars && \
    echo 'export APACHE_LOCK_DIR=/tmp' >> /etc/apache2/envvars && \
    echo 'export APACHE_LOG_DIR=/tmp' >> /etc/apache2/envvars && \
    echo 'export LANG=C' >> /etc/apache2/envvars && \
    echo 'export LANG=C' >> /etc/apache2/envvars

# Úprava samotné konfigurace, aby nehledala staré složky
RUN sed -i 's|ErrorLog ${APACHE_LOG_DIR}/error.log|ErrorLog /tmp/error.log|g' /etc/apache2/apache2.conf
RUN sed -i 's|CustomLog ${APACHE_LOG_DIR}/access.log combined|CustomLog /tmp/access.log combined|g' /etc/apache2/apache2.conf

# PHP session přesměrujeme do /tmp
RUN echo "session.save_path = '/tmp'" > /usr/local/etc/php/conf.d/session.ini
# ----------------------------------------

# Zkopírování tvých souborů do kontejneru
COPY . /var/www/html/

EXPOSE 80
