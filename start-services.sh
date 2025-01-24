#!/bin/bash

# Start Laravel queue worker
php artisan queue:work &

# Start npm dev server
npm run dev &

# Start Reverb
php artisan reverb:start &

# Start Apache server
apache2ctl -D FOREGROUND