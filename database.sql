CREATE DATABASE IF NOT EXISTS clothing_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clothing_bot;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(50) NOT NULL,
  clothing_type VARCHAR(100),
  design_image_url TEXT,
  size VARCHAR(20),
  color VARCHAR(100),
  preview_image_url TEXT,
  address TEXT,
  status VARCHAR(50) DEFAULT 'pending_price',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS convo_state (
  phone VARCHAR(50) PRIMARY KEY,
  last_message TEXT,
  last_response TEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  order_id INT,
  context_json TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
