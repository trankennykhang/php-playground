#!/bin/bash
cd /var/www/html
composer update
rr serve -c /var/www/html/.rr.yaml