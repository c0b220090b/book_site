FROM php:8.1-apache

# 必要なPHP拡張をインストール
RUN docker-php-ext-install pdo pdo_mysql

# Apacheの設定
RUN a2enmod rewrite

# 作業ディレクトリを設定
WORKDIR /var/www/html

# プロジェクトファイルをコピー
COPY . /var/www/html/

# 適切な権限を設定
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# ポート80を公開
EXPOSE 80
