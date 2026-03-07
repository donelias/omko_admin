release: "php artisan migrate --force"
web: vendor/bin/heroku-php-apache2 public/
queue: php artisan queue:work --quiet --tries=3 --timeout=90
