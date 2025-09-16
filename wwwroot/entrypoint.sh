#!/bin/bash
chown -R www-data:www-data /var/www/gmod
exec apache2-foreground
