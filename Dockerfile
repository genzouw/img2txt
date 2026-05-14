FROM genzouw/ansize:1.0.3

LABEL maintainer "genzouw <genzouw@gmail.com>"

# hadolint ignore=DL3018
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
    ; \
  sed -i '/#LoadModule deflate_module modules\/mod_deflate.so/s/^#//' /etc/apache2/httpd.conf

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -f http://localhost/ || exit 1

ENTRYPOINT ["/usr/sbin/httpd"]
CMD ["-D", "FOREGROUND"]
