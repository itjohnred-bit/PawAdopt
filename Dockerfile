# PawAdopt — production container for Render
# Render has NO native PHP runtime, so the app ships as a Docker image
# built from this Dockerfile (php:8.2-apache base).
#
# Render web services require the HTTP server to listen on the port given
# by the $PORT environment variable. Apache config files cannot read $PORT
# at build time (Render injects it at runtime), so the actual Listen
# directives are rewritten on every boot by docker/start.sh (the ENTRYPOINT).

FROM php:8.2-apache

# --- PHP extensions required by the application -----------------------------
# pdo_mysql = database access (PDO used by config/database.php)
# gd        = image processing for uploaded pet photos / avatars
# curl, mbstring, openssl etc. are already included in the php:8.2-apache base.
RUN docker-php-ext-install pdo_mysql mysqli gd \
    && a2enmod rewrite headers

# --- Production PHP settings: never show errors/stack traces to users -------
RUN { echo "display_errors = Off"; \
      echo "display_startup_errors = Off"; \
      echo "log_errors = On"; \
      echo "error_log = /dev/stderr"; \
      echo "expose_php = Off"; \
    } > /usr/local/etc/php/conf.d/pawadopt-prod.ini

# --- Application code --------------------------------------------------------
# The .dockerignore keeps the context small and prevents secrets (a real .env)
# from ever entering the image. Credentials are provided via the environment.
COPY . /var/www/html/

# Runtime entrypoint: rewrites Apache's Listen directive to $PORT on every
# boot and ensures the uploads directory is writable, then runs Apache.
COPY docker/start.sh /usr/local/bin/pawadopt-start.sh
RUN chmod +x /usr/local/bin/pawadopt-start.sh \
    && mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/pawadopt-start.sh"]
