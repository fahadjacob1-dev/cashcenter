FROM php:8.2-apache

# تفعيل الإضافات الضرورية لقواعد البيانات
RUN docker-php-ext-install mysqli pdo pdo_mysql

# تفعيل mod_rewrite مهم للروابط
RUN a2enmod rewrite

# إعداد الـ Port الخاص بمنصة Railway
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# نسخ ملفات المشروع وإعطاء الصلاحيات
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# الحل النهائي: إجبار السيرفر على تعطيل التعارض في لحظة التشغيل
CMD ["/bin/bash", "-c", "a2dismod mpm_event mpm_worker || true && a2enmod mpm_prefork && apache2-foreground"]
