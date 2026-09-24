#!/bin/sh
set -e

# O volume de diagramas é montado do host e pode pertencer a um UID
# diferente do www-data que roda os workers do PHP-FPM (ver docker/web
# não é afetado, só este container roda como root antes do php-fpm
# baixar privilégio por processo). Sem isso, criar/renomear/excluir
# Diagramas falha com "Permission denied" caso o diretório do host não
# tenha sido criado/ajustado para o UID do container.
chown -R www-data:www-data /var/www/html/storage/app/diagrams

php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force

exec "$@"
