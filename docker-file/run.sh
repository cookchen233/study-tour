#!/bin/sh

# redirect stdout and stderr to files
php /var/www/one-app/App/swoole.php > /var/www/one-app/console.log

# now run the requested CMD without forking a subprocess
exec "$@"