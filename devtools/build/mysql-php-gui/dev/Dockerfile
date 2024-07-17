FROM docker-registry.marktjagd.de/docker/mysql:5.5

COPY ./Dump20160620-1.sql /docker-entrypoint-initdb.d/00-init.sql
COPY ./Dump20160620-data.sql /docker-entrypoint-initdb.d/01-data.sql
ADD ./my.cnf /etc/mysql/my.cnf
