How to set up a local environment
=================================

1) Clone the git-repo from git.offerista.com/data-integration/crawler-php.git
2) Install required third party libraries manually via: composer require phpoffice/phpspreadsheet:1.13 aws/aws-sdk-php:3.143 google/apiclient:2.5 league/oauth2-google:3.0
3) Create a copy of the application/configs/application.ini.dist (without the .dist) and fill in how to connect to a (local) db
4) To test crawlers locally with xdebug and remote S3 support, set up a test-script in the IDE like so:

    File: crawler-php/scripts/testCrawler.php
    Arguments: Budni/Store 28980
    Environment-Variables:
        AWS_ACCESS_KEY_ID=<your aws api key>
        AWS_SECRET_ACCESS_KEY=<your aws api secret-key>
        AWS_ACCESS_ROLE=arn:aws:iam::385750204895:role/delegated-admin.di


How to run / test crawlers
==========================

General, automated process
--------------------------
1) The table "CrawlerConfig" determines with witch settings a given crawler-script should be executed based on a cron-expression.
2) On the production-server, the script "scripts/schedule.php" is invoked every minute via crontab, checking for crawlers which are due.
   Crawlers which are scheduled for the current minute are invoked by inserting a new entry into the table "CrawlerLog".
   Crawlers which have running imports are updated by polling the backend-api.
3) A python-script running on the production-server in the background ("/usr/local/bin/start_crawlers.py") continuously checks the "CrawlerLog" table for new entries.
   For each new record in the table, the script launches a new jenkins-job "start-crawler" with the CrawlerLog-ID as parameter.
4) The jenkins-job launches the script "scripts/init.php" with the CrawlerLog-ID as parameter.
5) The init-script then runs the actual crawler script and, if successful, creates an import in via the backend-API

Manual invocation of crawlers
-----------------------------
When not running in an IDE with already defined environment-variables (or on an AWS server), first set and export the personal AWS-credentials:
    AWS_ACCESS_KEY_ID=… AWS_ACCESS_ROLE=… AWS_SECRET_ACCESS_KEY=…
    export AWS_ACCESS_KEY_ID AWS_ACCESS_ROLE AWS_SECRET_ACCESS_KEY

To only test the crawling (e.g. produce a new import-file, but not actually import it), you can use "scripts/testCrawler.php":
    ./scripts/testCrawler.php Budni/Store 28980

To test the crawling and import, you can manually invoke a crawler by running "scripts/crawler.php", producing a new "CrawlerLog" entry:
    ./scripts/crawler.php stores 28980 testing
Then manually run "scripts/init.php" with the CrawlerLog-ID from the previous command, e.g.:
    ./scripts/init.php 426751
Optionally run "scripts/checkApiImports.php" to check for and update the status of the running imports started by the "init" srcipt.
    ./scripts/checkApiImports.php
