# Use an official PHP image with FPM
FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    libonig-dev \
    libzip-dev \
    libicu-dev \
    python3 \
    python3-pip \
    python3-opencv \
    supervisor \
    netcat-traditional \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pgsql pdo_pgsql mbstring exif pcntl bcmath opcache zip intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy PHP application files
COPY BD_PROYUSER/ ./

# Copy Python application files
COPY cripto_seguridad/ /app/cripto_seguridad/

# Create upload directory and set permissions
RUN mkdir -p /app/uploads && chmod -R 777 /app/uploads

# Install Python dependencies
RUN pip3 install --no-cache-dir --break-system-packages -r /app/cripto_seguridad/requirements.txt

# Configure PHP-FPM
COPY docker/php-fpm.conf /etc/php/8.1/fpm/php-fpm.conf
COPY docker/www.conf /etc/php/8.1/fpm/pool.d/www.conf

# Configure Supervisord
RUN mkdir -p /var/log/supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy and execute database initialization script
COPY docker/init_db.sh /usr/local/bin/init_db.sh
RUN chmod +x /usr/local/bin/init_db.sh
RUN /usr/local/bin/init_db.sh

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start Supervisord
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]