-- Separate databases for the automated tests (phpunit.xml forces DB_DATABASE=bookshelf_testing),
-- so running the test suite can never touch development data. The numbered ones let several streams of work
-- run the suite at the same time: set TEST_DB_DATABASE (see backend/tests/TestCase.php).
CREATE DATABASE IF NOT EXISTS bookshelf_testing
    CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS bookshelf_testing_2
    CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE IF NOT EXISTS bookshelf_testing_3
    CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON bookshelf_testing.* TO 'bookshelf'@'%';
GRANT ALL PRIVILEGES ON bookshelf_testing_2.* TO 'bookshelf'@'%';
GRANT ALL PRIVILEGES ON bookshelf_testing_3.* TO 'bookshelf'@'%';
