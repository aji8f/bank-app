-- MySQL initialization script for Simple Banking API
CREATE DATABASE IF NOT EXISTS simple_banking;
GRANT ALL PRIVILEGES ON simple_banking.* TO 'banking_user'@'%';
FLUSH PRIVILEGES;
