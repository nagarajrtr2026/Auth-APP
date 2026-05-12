-- Multi-Tenant User Authentication System — MySQL schema
-- Run: mysql -u root -p < schema_mysql.sql

CREATE DATABASE IF NOT EXISTS mt_auth CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mt_auth;

-- Users table (credentials). Tenant isolates accounts by organization/slug.
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id VARCHAR(64) NOT NULL DEFAULT 'default',
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email_tenant (email, tenant_id),
  KEY idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
