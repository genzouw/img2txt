FROM genzouw/ansize:1.0.2

LABEL maintainer "genzouw <genzouw@gmail.com>"

RUN apk add \
  --no-cache \
    apache2 \
    bash \
    curl \
    git \
    openssl \
    php7 \
    php7-apache2 \
    php7-iconv \
    php7-json \
    php7-openssl \
    php7-phar \
    tzdata \
    unzip \
    ;

RUN sed -i '/#LoadModule deflate_module modules\/mod_deflate.so/s/^#//' /etc/apache2/httpd.conf

ENTRYPOINT ["/usr/sbin/httpd"]
CMD ["-D", "FOREGROUND"]
