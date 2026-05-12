# Render deployment image for the AGX form.
# Auto-detected by Render when the repo is connected as a Docker web service.
FROM php:8.2-apache

# Enable URL rewriting.
RUN a2enmod rewrite

# PHP upload limits — form caps at 6 MB per photo.
RUN { \
    echo "post_max_size = 8M"; \
    echo "upload_max_filesize = 6M"; \
    echo "memory_limit = 128M"; \
  } > /usr/local/etc/php/conf.d/agx-uploads.ini

# Copy the application into Apache's docroot.
COPY . /var/www/html/

# Ensure writable directories exist and are owned by Apache.
RUN mkdir -p /var/www/html/data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/uploads \
    && chmod -R 755 /var/www/html/data /var/www/html/uploads

# Render assigns a port via $PORT (10000 by default). Rewrite Apache's config
# so it listens on whatever Render gives us.
RUN sed -ri -e 's!Listen 80!Listen ${PORT}!g' /etc/apache2/ports.conf \
    && sed -ri -e 's!\*:80!\*:${PORT}!g' /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

CMD ["apache2-foreground"]
