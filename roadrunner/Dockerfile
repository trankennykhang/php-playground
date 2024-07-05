FROM ghcr.io/roadrunner-server/roadrunner:2024.1.5 AS roadrunner
FROM php:8.3-cli

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

# USE THE RR