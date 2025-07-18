# Utilise une image PHP avec Apache
FROM php:8.2-apache

# Met à jour et installe les dépendances
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install zip pdo pdo_mysql

# Active le module Apache rewrite
RUN a2enmod rewrite headers

# Copie la configuration Apache personnalisée
COPY apache.conf /etc/apache2/sites-available/000-default.conf

# Copie les fichiers de l'application
COPY . /var/www/html/

# Définit les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Port exposé
EXPOSE 80

CMD ["apache2-foreground"]