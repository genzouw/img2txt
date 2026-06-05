# syntax=docker/dockerfile:1
# Stage 1: ansize (Go) を最新の Go + Alpine でビルドして単一バイナリ化する。
# 元実装は `FROM genzouw/ansize:1.0.3` (alpine 3.10.3 EOL) に依存しており、
# その base image に積み上がった CVE がそのまま img2txt に流れ込んでいたため、
# 自前ビルドに切り替えて外部 image への依存を断ち切る。
FROM golang:1.26-alpine3.22 AS ansize-builder

# hadolint ignore=DL3018
RUN apk add --no-cache git

WORKDIR /src
# jhchen/ansize は go.mod を持たない古い GOPATH 形式のため、
# clone 後にこちら側で go mod init + tidy して依存を解決する。
RUN git clone --depth=1 https://github.com/jhchen/ansize.git . \
  && go mod init ansize \
  && go mod tidy \
  && CGO_ENABLED=0 go build -ldflags='-s -w' -o /out/ansize .

# Stage 2: ランタイムは alpine 3.22 (現行サポート) + Apache + PHP 8.2
FROM alpine:3.23

LABEL maintainer="genzouw <genzouw@gmail.com>"

# hadolint ignore=DL3018
RUN apk add \
  --no-cache \
    apache2 \
    bash \
    curl \
    openssl \
    php82 \
    php82-apache2 \
    php82-iconv \
    php82-openssl \
    php82-phar \
    tzdata \
    unzip \
    ; \
  sed -i '/#LoadModule deflate_module modules\/mod_deflate.so/s/^#//' /etc/apache2/httpd.conf

RUN addgroup -g 1000 -S appgroup \
  && adduser -u 1000 -S -G appgroup appuser \
  && sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/httpd.conf \
  && sed -i 's/^User apache$/# User apache/' /etc/apache2/httpd.conf \
  && sed -i 's/^Group apache$/# Group apache/' /etc/apache2/httpd.conf \
  && mkdir -p /run/apache2 \
  && chown -R appuser:appgroup \
       /var/www/localhost \
       /var/log/apache2 \
       /run/apache2

COPY --from=ansize-builder /out/ansize /usr/local/bin/ansize

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -f http://localhost:8080/ || exit 1

USER appuser

ENTRYPOINT ["/usr/sbin/httpd"]
CMD ["-D", "FOREGROUND"]
