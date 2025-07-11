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

Create .env file by copying .env.example to .env and filling in the necessary values

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
This dependency is required for the `add_links.py` script used by
`PdfLinkAnnotatorService` to embed clickout links into downloaded PDFs.
If PyMuPDF is missing, the PDF will be downloaded but the annotation step
will fail and no links will be added. The service now checks for the
library at runtime and throws an explicit error if it's missing.
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

Set your AWS access keys in the `aws-credentials` file. If it doesn't exist, create it in the base directory of your project.:
```bash
[default]
aws_access_key_id = YOUR_LONG_TERM_KEY
aws_secret_access_key = YOUR_SECRET_KEY

[di-crawler]
role_arn = arn:aws:iam::385750204895:role/delegated-developer-crawler.di
source_profile = default
region = eu-west-1
```

The `docker-compose.yml` mounts this file at `/var/www/.aws/credentials` inside both the `app` and `worker` containers so AWS tools can automatically read it, even when processes run as the `www-data` user.
Ensure the file is world-readable so the containers can access it:
```bash
chmod 644 aws-credentials
```

Test Role Assumption:
Enter inside the app container and execute the following command (you may need to install the AWS CLI first):

```bash
aws sts get-caller-identity --profile di-crawler
```

## Useful Commands

- Check MySQL status: `sudo systemctl status mysql`
- Restart MySQL: `sudo systemctl restart mysql`
- Stop MySQL: `sudo systemctl stop mysql`

# 🐳 How to Run diCrawler with Docker

This guide explains how to build and run the project using Docker on **Linux**, **macOS**, or **Windows**.

---

## 📦 Prerequisites

- [Docker](https://www.docker.com/get-started)
- [Docker Compose](https://docs.docker.com/compose/install/)
- Ports `8001`, `3307`, and `3000` must be free.

---

## 🚀 Starting the App

First, create .env file by copying .env.example to .env and filling in the necessary values
Then execute one of the following commands:

```bash
make up       # for production (minimal)
```

```bash
make up-local # for local tools like Portainer/Grafana
```
Note for Windows Users: the "make" command only works in Unix Shell like [WSL](https://docs.microsoft.com/en-us/windows/wsl/install) CLI or Git Bash

This command will:

- Build the PHP container from your custom `Dockerfile`
- Build a matching worker container to run queued jobs
- The worker stays alive and polls for new presets every 10 seconds
- Wait for MySQL to be ready
- Drop, recreate and migrate the database
- Seed fixtures (e.g. admin user)
- Start Symfony web server at [http://127.0.0.1:8001](http://127.0.0.1:8001)
- Start Grafana at [http://127.0.0.1:3001](http://127.0.0.1:3001) (default login: `admin` / `admin`)

---

## 🛑 Stop and Clean Up

```bash
docker-compose down -v --remove-orphans
```

This stops and removes all containers, networks, and volumes.

---

## 📂 Directory Structure Summary

Make sure you have:

```
docker-compose.yml
docker/php/Dockerfile
docker/mysql/custom.cnf         # optional MySQL config
docker/cron/iproto-cron         # optional cron job
docker/cron/shopfully-cron      # runs presets cron job
```

---
## ⏲️ Cron Jobs

Cron definitions live in `docker/cron`. Enable the `shopfully-cron` file to run `app:shopfully:worker` on schedule. The command also responds to its
legacy name `app:shopfully:execute-presets` for backwards compatibility.


## 🧪 Verify App is Running

- Symfony App: [http://127.0.0.1:8001](http://127.0.0.1:8001)
- Grafana: [http://127.0.0.1:3000](http://127.0.0.1:3000)
- MySQL: connect via port `3307`, user `root`, pass `1203`, DB `dicrawler`

---

## 🐘 App Admin User (pre-seeded via fixtures)

- **Email:** `admin@admin.com`
- **Password:** `admin`

---

## 🐞 Troubleshooting

- ❗ **Port Already in Use:** make sure ports `8001`, `3307`, and `3000` are free.
- ❗ **MySQL Connection Refused:** double check `MYSQL_ROOT_PASSWORD` and wait-for-it logic.
- ❗ **Symfony "Access Denied" on /api/** – make sure your user is logged in or endpoint is publicly accessible.

---
