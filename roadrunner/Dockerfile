FROM do:2024.1.5 AS roadrunner
FROM php:8.3-cli

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr
COPY composer /usr/local/bin/
EXPOSE 80
EXPOSE 443
# USE THE RR
ENTRYPOINT ["rr", "-c", ".rr.yaml"]