#!/bin/bash

# Update package lists
apt-get update

# Install PHP and common extensions
apt-get install -y php php-cli php-curl php-mbstring php-xml unzip curl git

# Optional: install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Confirm installs
php -v
composer -V
