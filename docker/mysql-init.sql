-- Separate database for the test suite (phpunit.xml uses portfolio_test).
CREATE DATABASE IF NOT EXISTS portfolio_test;
GRANT ALL ON portfolio_test.* TO 'portfolio'@'%';
