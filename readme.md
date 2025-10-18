# ASE 230 Project 1

**Name:** Landon Patton  
**Class:** ASE 230  
**Date:** October 2025  

---

## Overview
This project implements a RESTful API using PHP, MySQL, and Nginx running under WSL on Windows 11.

Endpoints:
- `POST /users` – register new users  
- `POST /login` – login and get auth token  
- `POST /items` – create item (requires token)  
- `GET /items` – list items  
- `GET /items/{id}` – view single item  
- `PUT /items/{id}` – update item  
- `DELETE /items/{id}` – delete item  

---

## Environment
- **OS:** Ubuntu via WSL 2 on Windows 11  
- **Web Server:** Nginx 1.24.0  
- **PHP:** 8.3 FPM  
- **Database:** MySQL 8  
- **Local URL:** [http://127.0.0.1](http://127.0.0.1)

---

## Testing
- HTML test page: `/index.html`
- cURL test script: `code/tests/curl_tests.sh`

---

## Deployment
- Nginx configured at `/etc/nginx/sites-available/project1`
- Root path: `/home/lando/projects/project1-restapi/code/public`
- PHP-FPM socket: `/run/php/php8.3-fpm.sock`
- `.env` file defines database credentials.