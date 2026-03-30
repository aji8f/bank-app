# Simple Banking API Simulator

A production-ready Laravel Banking API designed for AWS deployment with WAF, Auto Scaling, and Blue/Green deployments via CodeDeploy.

---

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Local Setup](#local-setup)
- [API Endpoints](#api-endpoints)
- [Database Schema](#database-schema)
- [Docker](#docker)
- [AWS Deployment](#aws-deployment)
- [Load Testing](#load-testing)
- [WAF Testing](#waf-testing)
- [Error Codes](#error-codes)

---

## Overview

This application simulates a banking backend with:

- Balance management (deposit, transfer, query)
- Transaction history
- Atomic database operations (no partial transfers)
- Input validation and SQL injection protection via Eloquent ORM
- Rate limiting headers
- Docker containerization
- AWS CodeBuild + CodeDeploy CI/CD pipeline

---

## Architecture

```
                        Internet
                            |
                     +------+------+
                     |  AWS WAF    |  (SQL injection, XSS, rate limiting)
                     +------+------+
                            |
               +------------+------------+
               |  Application Load Balancer |
               +------+--------+-----------+
                      |        |
           +----------+--+  +--+----------+
           | EC2/ECS App |  | EC2/ECS App |   <- Auto Scaling Group
           | (Blue Env)  |  | (Green Env) |   <- Blue/Green Deployment
           +------+------+  +------+------+
                  |                |
                  +-------+--------+
                          |
               +----------+----------+
               |   Amazon RDS MySQL  |  (Multi-AZ, encrypted)
               +---------------------+
                          |
               +----------+----------+
               |   Amazon ElastiCache|  (Redis - rate limiting, sessions)
               +---------------------+

CI/CD Pipeline:
  CodeCommit --> CodeBuild --> ECR --> CodeDeploy (Blue/Green)
                    |
               Unit Tests
               Docker Build
               Push to ECR
```

---

## Local Setup

### Prerequisites

- PHP >= 8.2
- Composer
- MySQL 8.0 (or use Docker)
- Redis (optional, for rate limiting)

### Option 1: Local PHP (without Docker)

```bash
# 1. Clone the project
cd c:/Users/dians/Documents/CODING/simple-banking

# 2. Install PHP dependencies
composer install

# 3. Copy environment file and configure
cp .env.example .env
# Edit .env: set DB_* variables to your local MySQL

# 4. Generate application key
php artisan key:generate

# 5. Create the database in MySQL
mysql -u root -p -e "CREATE DATABASE simple_banking;"

# 6. Run migrations
php artisan migrate

# 7. Seed sample users
php artisan db:seed

# 8. Start the server
php artisan serve

# API is now at: http://localhost:8000/api
```

### Option 2: Docker Compose

```bash
# 1. Build and start all services (app + mysql + redis)
docker-compose up -d --build

# 2. Run migrations inside the container
docker exec simple-banking-app php artisan migrate --seed

# 3. Check status
docker-compose ps

# API is now at: http://localhost:8000/api
# phpMyAdmin at: http://localhost:8080 (run with --profile debug)
```

---

## API Endpoints

All endpoints return JSON. No authentication required (for testing).

Base URL: `http://localhost:8000/api`

---

### GET /health

Health check. Returns application and database status.

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2024-01-15T10:30:00+00:00",
  "db": "connected"
}
```

---

### GET /users

List all users (for testing purposes).

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Alice Johnson",
      "email": "alice@example.com",
      "balance": 1000000.00,
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "total": 5
}
```

---

### GET /balance/{user_id}

Get the balance for a specific user.

**Parameters:**
- `user_id` (integer, required) - The user's ID

**Response:**
```json
{
  "user_id": 1,
  "name": "Alice Johnson",
  "email": "alice@example.com",
  "balance": 1000000.00
}
```

**Error (404):**
```json
{
  "error": "User not found",
  "code": "USER_NOT_FOUND"
}
```

**Example:**
```bash
curl http://localhost:8000/api/balance/1
```

---

### POST /deposit

Deposit money into a user's account.

**Request Body:**
```json
{
  "user_id": 1,
  "amount": 50000
}
```

**Response (201):**
```json
{
  "message": "Deposit successful",
  "transaction_id": 1,
  "user_id": 1,
  "amount": 50000,
  "new_balance": 1050000.00
}
```

**Example:**
```bash
curl -X POST http://localhost:8000/api/deposit \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "amount": 50000}'
```

---

### POST /transfer

Transfer funds between two users. Uses atomic database transactions.

**Request Body:**
```json
{
  "from": 1,
  "to": 2,
  "amount": 10000
}
```

**Response (200):**
```json
{
  "message": "Transfer successful",
  "transaction_id": 2,
  "from": {
    "user_id": 1,
    "name": "Alice Johnson",
    "new_balance": 990000.00
  },
  "to": {
    "user_id": 2,
    "name": "Bob Smith",
    "new_balance": 510000.00
  },
  "amount": 10000,
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

**Errors:**
```json
{ "error": "Insufficient balance", "code": "INSUFFICIENT_BALANCE" }
{ "error": "User not found", "code": "USER_NOT_FOUND" }
{ "error": "Invalid amount", "code": "INVALID_AMOUNT" }
```

**Example:**
```bash
curl -X POST http://localhost:8000/api/transfer \
  -H "Content-Type: application/json" \
  -d '{"from": 1, "to": 2, "amount": 10000}'
```

---

### GET /transactions/{user_id}

Get transaction history for a user (sent and received).

**Response:**
```json
{
  "user_id": 1,
  "name": "Alice Johnson",
  "data": [
    {
      "id": 2,
      "type": "transfer",
      "amount": 10000.00,
      "status": "success",
      "direction": "debit",
      "from_user": {
        "id": 1,
        "name": "Alice Johnson",
        "email": "alice@example.com"
      },
      "to_user": {
        "id": 2,
        "name": "Bob Smith",
        "email": "bob@example.com"
      },
      "note": "Transfer of 10000 from user 1 to user 2",
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "total": 1
}
```

---

## Database Schema

### users

| Column     | Type           | Notes                    |
|------------|----------------|--------------------------|
| id         | bigint PK      | Auto increment           |
| name       | varchar(255)   | Full name                |
| email      | varchar(255)   | Unique                   |
| balance    | decimal(15,2)  | Current balance          |
| created_at | timestamp      |                          |
| updated_at | timestamp      |                          |

### transactions

| Column       | Type                              | Notes                         |
|--------------|-----------------------------------|-------------------------------|
| id           | bigint PK                         | Auto increment                |
| from_user_id | bigint FK (nullable)              | Sender (null for deposits)    |
| to_user_id   | bigint FK (nullable)              | Receiver                      |
| type         | enum(transfer, deposit, withdrawal)| Transaction type             |
| amount       | decimal(15,2)                     | Amount                        |
| status       | enum(success, failed)             | Result                        |
| note         | varchar(255) nullable             | Description                   |
| created_at   | timestamp                         |                               |
| updated_at   | timestamp                         |                               |

### Sample Data (after seeding)

| ID | Name           | Email               | Balance       |
|----|----------------|---------------------|---------------|
| 1  | Alice Johnson  | alice@example.com   | 1,000,000.00  |
| 2  | Bob Smith      | bob@example.com     | 500,000.00    |
| 3  | Charlie Brown  | charlie@example.com | 250,000.00    |
| 4  | Diana Prince   | diana@example.com   | 750,000.00    |
| 5  | Eve Adams      | eve@example.com     | 100,000.00    |

---

## Docker

### Build image

```bash
docker build -t simple-banking-api .
```

### Run with Docker Compose

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f app

# Stop all services
docker-compose down

# Stop and remove volumes (full reset)
docker-compose down -v
```

### Docker services

| Service     | Port  | Description         |
|-------------|-------|---------------------|
| app         | 8000  | Laravel application |
| mysql       | 3306  | MySQL 8.0 database  |
| redis       | 6379  | Redis cache         |
| phpmyadmin  | 8080  | DB UI (debug only)  |

---

## AWS Deployment

### Architecture Components

- **AWS CodeCommit** - Source code repository
- **AWS CodeBuild** - CI/CD build and test (`buildspec.yml`)
- **Amazon ECR** - Docker image registry
- **AWS CodeDeploy** - Blue/Green deployment (`appspec.yml`)
- **Application Load Balancer** - Traffic routing and SSL termination
- **Auto Scaling Group** - Dynamic scaling based on CPU/request metrics
- **Amazon RDS (MySQL)** - Multi-AZ managed database
- **Amazon ElastiCache (Redis)** - Session and rate limit cache
- **AWS WAF** - Web Application Firewall rules

### CI/CD Pipeline

```
git push --> CodeCommit
                 |
            CodeBuild
            - composer install
            - php artisan test
            - docker build
            - docker push ECR
                 |
            CodeDeploy
            - before_install.sh
            - after_install.sh (run migrations)
            - start_server.sh
            - validate_service.sh
                 |
            Blue/Green swap
            (zero-downtime deploy)
```

### Parameter Store Keys

```
/simple-banking/app-key        - Laravel APP_KEY
/simple-banking/db-password    - RDS database password
/simple-banking/rds-endpoint   - RDS hostname
```

### WAF Rules (recommended)

- AWSManagedRulesCommonRuleSet
- AWSManagedRulesSQLiRuleSet
- AWSManagedRulesKnownBadInputsRuleSet
- Rate limiting: 2000 requests per 5 minutes per IP

---

## Load Testing

The `load-test.sh` script simulates high concurrent traffic to trigger Auto Scaling.

```bash
# Basic usage (200 concurrent, 60 seconds)
./load-test.sh http://your-alb-url.com

# Custom concurrent and duration
./load-test.sh http://your-alb-url.com 500 120

# Local testing
./load-test.sh http://localhost:8000 50 30
```

**What it does:**
- Sends 200 concurrent requests per wave
- Rotates through all endpoints (health, balance, users, transactions, deposit)
- Runs for the specified duration
- Reports requests/sec and success rate

---

## WAF Testing

The `waf-test.sh` script validates that SQL injection and other attacks are blocked.

```bash
# Test local instance
./waf-test.sh http://localhost:8000

# Test deployed instance (with WAF)
./waf-test.sh http://your-alb-url.com
```

**Test categories:**
1. Baseline requests (should pass)
2. SQL injection in URL path
3. SQL injection in POST body
4. XSS attempts
5. Path traversal
6. Business logic validation
7. Malformed requests

---

## Error Codes

| Code                   | HTTP | Description                          |
|------------------------|------|--------------------------------------|
| USER_NOT_FOUND         | 404  | The specified user does not exist    |
| INSUFFICIENT_BALANCE   | 422  | Sender has insufficient funds        |
| INVALID_AMOUNT         | 422  | Amount is invalid (zero, negative)   |
| SAME_USER_TRANSFER     | 422  | Cannot transfer to the same account  |
| VALIDATION_ERROR       | 422  | General input validation failure     |

---

## Security Notes

- All database queries use Eloquent ORM (no raw SQL)
- Input validated with Laravel Validator before processing
- Database transfers use `DB::transaction()` with row-level locking (`lockForUpdate()`)
- Rate limiting headers included in all responses
- Security headers: `X-Frame-Options`, `X-Content-Type-Options`
- Never exposes stack traces in production (`APP_DEBUG=false`)

---

## License

MIT License - for educational and testing purposes.
