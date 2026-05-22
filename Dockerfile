FROM php:8.2-apache

# تفعيل الإضافات الضرورية للاتصال بقاعدة بيانات MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# تفعيل mod_rewrite الخاص بـ Apache
RUN a2enmod rewrite

# نسخ كل ملفات المشروع
COPY . /var/www/html/

# تعديل إعدادات Apache حتى يستمع للـ Port اللي يحدده Railway
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# إعطاء الصلاحيات الصحيحة
RUN chown -R www-data:www-data /var/www/html
