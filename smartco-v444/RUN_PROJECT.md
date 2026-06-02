# How to Run SmartCo

## Requirements

- XAMPP
- Node.js
- Angular CLI
- MySQL / MariaDB

## Backend

Copy the backend folder to:

C:/xampp/htdocs/backend
Start XAMPP:

Apache: Start
MySQL: Start
Database
Database name:
scm
Make sure the database exists in MySQL.

Database connection file:

backend/lib/db.php
Default database settings:

host: 127.0.0.1
database: scm
user: root
password: 1234
Frontend
Open terminal in:
C:/Users/Update/Downloads/smartco-v444

Run:
ng serve --proxy-config proxy.conf.json --port 4200

Open in browser:

http://localhost:4200

Login
Use an existing active user from the database.