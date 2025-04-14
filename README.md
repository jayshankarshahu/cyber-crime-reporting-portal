# Cyber Crime Reporting Portal

## Prerequisites

Make sure the following are installed and configured on your system before running the project:

- **PHP** (>= 7.x)
- **MySQL** (or MariaDB)
- **PHP PDO extension**
- **PHP MySQL driver**
- Import the database schema from [db-schema.sql](https://github.com/jayshankarshahu/cyber-crime-reporting-portal/blob/main/db-schema.sql) into your MySQL server

## How to Run

Follow these steps to run the project locally:

```bash
# Clone the repository
git clone https://github.com/jayshankarshahu/cyber-crime-reporting-portal.git

# Change directory into the project folder
cd cyber-crime-reporting-portal

# Install dependencies
composer update
```

### Create a .env file

```bash
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password
```

### Start the PHP built-in server

```bash
php -S localhost:3000
```