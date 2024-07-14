FROM ghcr.io/roadrunner-server/roadrunner:2024.1.5 AS roadrunner
FROM php:8.3-cli

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
COPY composer /usr/local/bin/
RUN apt update
RUN apt install -y zip unzip libzip-dev
RUN docker-php-ext-install sockets
RUN docker-php-ext-install zip
RUN apt install -y libsodium-dev
RUN docker-php-ext-install sodium
EXPOSE 8080
EXPOSE 443
# USE THE RR
ENTRYPOINT ["bash", "/var/www/html/run.sh"]
#ENTRYPOINT ["tail", "-f", "/dev/null"]