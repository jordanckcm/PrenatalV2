FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

# Force-remove any mpm_event config, force-enable mpm_prefork only
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf \
    && a2enmod rewrite

# Debug: print what's actually enabled at build time
RUN ls -la /etc/apache2/mods-enabled/ | grep mpm

COPY . /var/www/html/

EXPOSE 8080

CMD ["/bin/bash", "-c", "sed -i \"s/80/$PORT/g\" /etc/apache2/ports.conf /etc/apache2/sites-enabled/000-default.conf && apache2-foreground"]
