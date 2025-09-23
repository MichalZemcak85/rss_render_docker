FROM php:8.1-cli

# Zkopíruj PHP skript do kontejneru
COPY rss.php /var/www/html/rss.php

# Nastav pracovní adresář
WORKDIR /var/www/html

# Spusť PHP server
CMD ["php", "-S", "0.0.0.0:10000", "rss.php"]
