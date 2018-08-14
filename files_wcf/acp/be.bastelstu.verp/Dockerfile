FROM	timwolla/php:wcf

COPY	verp.c /
RUN	gcc -o /verp /verp.c

USER	www-data
ENTRYPOINT	[ "/verp" ]
