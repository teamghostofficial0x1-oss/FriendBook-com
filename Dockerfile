# ==========================================
# ১. বেস ইমেজ (PHP 8.2 এর সাথে Apache প্রোডাকশন ইমেজ)
# ==========================================
FROM php:8.2-apache

# ইন্টারনাল ডেবিয়ান প্রম্পট বন্ধ করা
ENV DEBIAN_FRONTEND=noninteractive

# ==========================================
# ২. সিস্টেম ডিপেন্ডেন্সি ও PostgreSQL এক্সটেনশন ইনস্টল (Fix Driver Error)
# ==========================================
RUN apt-get update && apt-get install -y \
    libpq-dev \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && docker-php-ext-enable pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*

# ==========================================
# ৩. অ্যাপাচি কনফিগারেশন (Mod_Rewrite এনাবল করা)
# ==========================================
RUN a2enmod rewrite

# অ্যাপাচির ডিফল্ট রুট পরিবর্তন করে /app ডিরেক্টরি করা
ENV APACHE_DOCUMENT_ROOT /app
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# ==========================================
# ৪. ওয়ার্কিং ডিরেক্টরি ও কোড কপি
# ==========================================
WORKDIR /app

# প্রথমে রুট রেপোর সব ফাইল কপি করা
COPY . .

# ==========================================
# ৫. সিকিউরিটি ও পারমিশন সেটআপ
# ==========================================
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app

# ==========================================
# ৬. পোর্ট এবং রান কমান্ড
# ==========================================
EXPOSE 80

CMD ["apache2-foreground"]
