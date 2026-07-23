#!/bin/sh
set -e

php artisan queue:work
