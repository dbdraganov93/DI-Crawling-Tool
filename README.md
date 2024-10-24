# DI - Crawler
Crawls things.


## Getting started
Run codestyle localy: vendor/bin/phpcs --standard=PSR12 src/


Run codestyle with the custom config: vendor/bin/phpcs
Full report: vendor/bin/phpcs --standard=PSR12 --report=full src/
Diff: vendor/bin/phpcs --standard=PSR12 --report=diff src/

Fix codestyle: vendor/bin/phpcbf --standard=PSR12 src/

To make it easy for you to get started with GitLab, here's a list of recommended next steps.

Already a pro? Just edit this README.md and make it your own. Want to make it easy? [Use the template at the bottom](#editing-this-readme)!

## Add your files

- [ ] [Create](https://docs.gitlab.com/ee/user/project/repository/web_editor.html#create-a-file) or [upload](https://docs.gitlab.com/ee/user/project/repository/web_editor.html#upload-a-file) files
- [ ] [Add files using the command line](https://docs.gitlab.com/ee/gitlab-basics/add-file.html#add-a-file-using-the-command-line) or push an existing Git repository with the following command:

```
cd existing_repo
git remote add origin git@git.offerista.com:dragan.draganov/testingcrawlerpipe.git
git branch -M master
git push -uf origin master
```
## Install
Pull the repository

Install dependencies:
```
composer install
```

Configure DB in .env

Generate migration with command
```
php bin/console make:migration
```
Migrate
```
php bin/console doctrine:migrations:migrate
```
Run the server
```
symfony serve -d
```
