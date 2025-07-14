# Étape 1 : base PHP CLI avec curl (utile pour file_get_contents https)
FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
 && docker-php-ext-install curl \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY exporter.php /app/exporter.php

# Exposer le port 8080 (tu peux changer)
EXPOSE 9100

# Commande pour lancer le serveur PHP intégré, accessible sur 0.0.0.0
CMD ["php", "-S", "0.0.0.0:9100", "-t", "/app"]
