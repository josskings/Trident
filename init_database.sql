-- Restaurant Queue Management System Database Initialization Script

-- Create database
CREATE DATABASE IF NOT EXISTS restaurant_queue;
USE restaurant_queue;

-- Create tables
CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_role (role)
);

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phone_number VARCHAR(20) NOT NULL UNIQUE,
  no_show_count INT DEFAULT 0,
  blacklisted BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_phone_number (phone_number),
  INDEX idx_blacklisted (blacklisted)
);

CREATE TABLE IF NOT EXISTS table_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  min_capacity INT NOT NULL,
  max_capacity INT NOT NULL,
  description VARCHAR(255),
  INDEX idx_capacity (min_capacity, max_capacity)
);

CREATE TABLE IF NOT EXISTS queue_tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_number INT NOT NULL,
  customer_id INT NOT NULL,
  table_type_id INT NOT NULL,
  party_size INT NOT NULL,
  queue_date DATE NOT NULL,
  queue_time TIMESTAMP NOT NULL,
  status ENUM('waiting', 'seated', 'no_show', 'cancelled') NOT NULL,
  is_remote BOOLEAN NOT NULL,
  waiting_count_at_creation INT NOT NULL,
  seated_time TIMESTAMP NULL,
  verification_code VARCHAR(6),
  verification_status BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_queue_date_number (queue_date, ticket_number),
  INDEX idx_queue_date_status (queue_date, status),
  INDEX idx_customer_id (customer_id),
  INDEX idx_table_type_id (table_type_id),
  INDEX idx_status (status),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (table_type_id) REFERENCES table_types(id)
);

CREATE TABLE IF NOT EXISTS sms_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  queue_ticket_id INT NOT NULL,
  message_content TEXT NOT NULL,
  sent_time TIMESTAMP NOT NULL,
  status ENUM('sent', 'failed', 'delivered') NOT NULL,
  INDEX idx_customer_id (customer_id),
  INDEX idx_queue_ticket_id (queue_ticket_id),
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (queue_ticket_id) REFERENCES queue_tickets(id)
);

CREATE TABLE IF NOT EXISTS verification_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  code VARCHAR(6) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  used BOOLEAN DEFAULT FALSE,
  INDEX idx_customer_id (customer_id),
  INDEX idx_code (code),
  INDEX idx_expires_at (expires_at),
  FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS queue_status (
  id INT AUTO_INCREMENT PRIMARY KEY,
  queue_date DATE NOT NULL UNIQUE,
  current_number_small INT DEFAULT 0,
  current_number_medium INT DEFAULT 0,
  current_number_large INT DEFAULT 0,
  last_issued_small INT DEFAULT 0,
  last_issued_medium INT DEFAULT 0,
  last_issued_large INT DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_queue_date (queue_date)
);

CREATE TABLE IF NOT EXISTS queue_statistics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  queue_date DATE NOT NULL,
  table_type_id INT NOT NULL,
  is_peak_hour BOOLEAN NOT NULL,
  avg_wait_time INT,
  total_customers INT,
  no_show_count INT,
  remote_queue_count INT,
  onsite_queue_count INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_queue_date (queue_date),
  INDEX idx_table_type_id (table_type_id),
  INDEX idx_is_peak_hour (is_peak_hour),
  FOREIGN KEY (table_type_id) REFERENCES table_types(id)
);

-- Insert sample data
-- Table types
-- 使用 INSERT IGNORE 避免重複插入
INSERT IGNORE INTO table_types (name, min_capacity, max_capacity, description) VALUES
('Small', 1, 2, 'Tables for 1-2 people'),
('Medium', 3, 4, 'Tables for 3-4 people'),
('Large', 5, 10, 'Tables for 5+ people');

-- Employees
-- 使用 INSERT IGNORE 避免重複插入
INSERT IGNORE INTO employees (name, username, password_hash, role) VALUES
('Admin User', 'admin', '$2y$10$zF5BuRQzW.wNSUDmJXgKIeO6EAK2xqWRbZj4Y.K9.IvVJlKP3vTLu', 'admin'),
('Host Staff', 'host1', '$2y$10$zF5BuRQzW.wNSUDmJXgKIeO6EAK2xqWRbZj4Y.K9.IvVJlKP3vTLu', 'host'),
('Wait Staff', 'waiter1', '$2y$10$zF5BuRQzW.wNSUDmJXgKIeO6EAK2xqWRbZj4Y.K9.IvVJlKP3vTLu', 'waiter');

-- Initialize queue status for today
-- 使用 INSERT IGNORE 避免重複插入
INSERT IGNORE INTO queue_status (queue_date, current_number_small, current_number_medium, current_number_large, 
                         last_issued_small, last_issued_medium, last_issued_large)
VALUES (CURDATE(), 1, 1, 1, 3, 2, 1);
