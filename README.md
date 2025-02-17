docker compose build
docker compose run --rm php-app-231 composer install && docker compose run --rm php-app-231 php benchmark.php
docker compose run --rm php-app-241 composer install && docker compose run --rm php-app-241 php benchmark.php
