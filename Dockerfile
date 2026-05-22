FROM php:8.2-apache

# تفعيل الإضافات الضرورية
RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && a2enmod rewrite

# نسخ ملفات المشروع وإعطاء الصلاحيات
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# الحل الشامل:
# 1. تحويل رقم بورت Apache إلى رقم البورت الحقيقي الخاص بـ Railway
# 2. حل مشكلة الـ MPM
# 3. تشغيل السيرفر
CMD ["/bin/bash", "-c", "sed -i \"s/80/$PORT/g\" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf && a2dismod mpm_event mpm_worker || true && a2enmod mpm_prefork && apache2-foreground"]
