# Použijeme PHP s Apachem
FROM php:8.2-apache

# Instalace potøebných rozšíøení pro komunikaci s databází a AI
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && docker-php-ext-install curl

# Povolení mod_rewrite pro Apache (užiteèné pro èistá URL)
RUN a2enmod rewrite

# Zkopírování tvých souborù do kontejneru
COPY . /var/www/html/

# Nastavení práv, aby PHP mohlo zapisovat
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80