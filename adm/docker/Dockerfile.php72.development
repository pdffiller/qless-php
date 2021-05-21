FROM php:7.2-alpine

LABEL maintainer="Serghei Iakovlev <i.serghei@pdffiller.com>"

RUN apk update \
    && apk upgrade \
    && apk add --no-cache --update --virtual buildDeps ${PHPIZE_DEPS} \
    && pecl install -o -f redis \
    && pecl install proctitle-0.1.2 \
    && docker-php-ext-enable redis \
    && docker-php-ext-install sockets \
    && docker-php-ext-install pcntl \
    && php -m | grep -e 'redis\|proctitle\|json\|pcntl\|posix\|sockets' \
    && apk del buildDeps \
    && rm -rf \
        /var/cache/apk/* \
        /tmp/* \
        ~/.pearrc \
    && find /var/log -type f | while read f; do echo -ne '' > ${f} >/dev/null 2>&1 || true; done

WORKDIR /app
