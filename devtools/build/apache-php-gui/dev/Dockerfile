FROM ubuntu:14.04

VOLUME ["/var/www"]

RUN apt-get update && apt-get install -y apache2 php5 php5-cli libapache2-mod-php5 php5-gd php5-json php5-mysql php5-curl php5-imap php5-tidy php5-intl php5-mcrypt php5-sqlite php-pear

RUN pear install -a net_smtp mail_mime

COPY php.ini /etc/php5/apache2/php.ini
COPY php.ini /etc/php5/cli/php.ini

COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

#COPY solr.so /usr/lib/php5/20090626/solr.so
#COPY solr.ini /etc/php5/conf.d/solr.ini

RUN a2enmod rewrite

EXPOSE 80
CMD ["apachectl", "-DFOREGROUND"]
