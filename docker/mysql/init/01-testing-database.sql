-- Separate database for the automated tests (phpunit.xml forces DB_DATABASE=bookshelf_testing),
-- so running the test suite can never touch development data.
CREATE DATABASE IF NOT EXISTS bookshelf_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON bookshelf_testing.* TO 'bookshelf'@'%';
