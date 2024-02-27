FROM php:8.3.3-fpm-alpine

WORKDIR /var/www/html

ENV VERSION=v0.0.1

# nginx for contents
RUN apk --update --no-cache add nginx

RUN curl -o pukiwiki-${VERSION}.tar.gz -L https://github.com/nomura-kei/pukiwiki/archive/refs/tags/${VERSION}.tar.gz && \
 tar zxf pukiwiki-*.tar.gz && \
 mv pukiwiki-*/* pukiwiki-*/.ht* . && \
 rm -rf pukiwiki-* *.txt *.zip *.tar.gz

# default read only
RUN sed -i -e "s/^\/\/\(define.*PKWK_READONLY.*1.*;\)/\1/" index.php
RUN sed -i -e "s/^user nginx;/user www-data;/" /etc/nginx/nginx.conf

# other settings
COPY rootfs/ /

RUN chown -R www-data:www-data /var/www/html

EXPOSE 9001

CMD ["/usr/local/sbin/pukiwiki"]
