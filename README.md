# DI - Crawler
Crawls things.


## Getting started
Run codestyle localy: 
```
vendor/bin/phpcs --standard=PSR12 src/
```

Run codestyle with the custom config: 
```
vendor/bin/phpcs
```
Full report: 
```
vendor/bin/phpcs --standard=PSR12 --report=full src/
```
Diff codestyle: 
```
vendor/bin/phpcs --standard=PSR12 --report=diff src/
```

Fix codestyle: 
```
vendor/bin/phpcbf --standard=PSR12 src/
```

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
Install PyMuPDF
```
pip install PyMuPDF
```
Run the server
```
symfony serve -d
```

# MySQL Setup on Ubuntu

This guide provides steps to install, secure, and configure a MySQL database on Ubuntu, including creating a UTF8 `diCrawlers` database and a dedicated user.

## Step 1: Install MySQL

```bash
sudo apt update
sudo apt install mysql-server -y
```

## Step 2: Secure MySQL Installation

Run the following script to set root password and apply basic security measures:

```bash
sudo mysql_secure_installation
```

Recommended responses:
- Validate password plugin: `Y` or `N` (your choice)
- Set root password: `Y`
- Remove anonymous users: `Y`
- Disallow root login remotely: `Y`
- Remove test database: `Y`
- Reload privilege tables: `Y`

## Step 3: Start and Enable MySQL

```bash
sudo systemctl start mysql
sudo systemctl enable mysql
```

## Step 4: Log into MySQL

```bash
sudo mysql
```

## Step 5: Create Database, User and Set UTF8

```sql
-- Create the database with UTF8 General CI collation
CREATE DATABASE diCrawlers CHARACTER SET utf8 COLLATE utf8_general_ci;

-- Create a user with a strong password
CREATE USER 'diUser'@'localhost' IDENTIFIED BY 'StrongPassword123!';

-- Grant privileges on the new database
GRANT ALL PRIVILEGES ON diCrawlers.* TO 'diUser'@'localhost';

-- Apply changes
FLUSH PRIVILEGES;
```

To exit MySQL CLI:

```sql
EXIT;
```

## Step 6: Test Connection

Login as the new user:

```bash
mysql -u diUser -p diCrawlers
```

## Step 6: Setup AWS

```bash
sudo apt update
sudo apt install awscli
mkdir -p ~/.aws
nano ~/.aws/credentials
```

Paste this inside (replace with your real AWS keys from the root account):
```bash
[default]
aws_access_key_id = YOUR_LONG_TERM_KEY
aws_secret_access_key = YOUR_SECRET_KEY
```

```bash
nano ~/.aws/config
```

```bash
[profile di-crawler]
source_profile = default
role_arn = arn:aws:iam::385750204895:role/delegated-developer-crawler.di
region = eu-west-1
output = json
```

Test Role Assumption

```bash
aws sts get-caller-identity --profile di-crawler
```

## Useful Commands

- Check MySQL status: `sudo systemctl status mysql`
- Restart MySQL: `sudo systemctl restart mysql`
- Stop MySQL: `sudo systemctl stop mysql`