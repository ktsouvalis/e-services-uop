#!/bin/bash

# Start Laravel queue worker
php artisan queue:work &

# Start npm dev server
npm run build &

# Start Reverb
php artisan reverb:start &

# Start Apache server
apache2ctl -D FOREGROUND