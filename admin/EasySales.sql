CREATE TABLE `EASYSALLES_CATEGORIES` (
  `category_id` int NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text,
  `color` varchar(20) DEFAULT '#06B6D4',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
)  

CREATE TABLE `EASYSALLES_INVENTORY_LOG` (
  `log_id` int NOT NULL,
  `product_id` int NOT NULL,
  `change_type` enum('stock_in','stock_out','adjustment','damage','return') NOT NULL,
  `quantity_change` int NOT NULL,
  `previous_stock` int NOT NULL,
  `new_stock` int NOT NULL,
  `reference_id` int DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `notes` text,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
)  

CREATE TABLE `EASYSALLES_PRODUCTS` (
  `product_id` int NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text,
  `category` varchar(100) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `current_stock` int DEFAULT '0',
  `min_stock` int DEFAULT '10',
  `max_stock` int DEFAULT '100',
  `unit_type` varchar(50) DEFAULT 'piece',
  `image_url` varchar(255) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','discontinued') DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)  

CREATE TABLE `EASYSALLES_SALES` (
  `sale_id` int NOT NULL,
  `transaction_code` varchar(50) NOT NULL,
  `customer_name` varchar(100) DEFAULT 'Walk-in Customer',
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `subtotal_amount` decimal(10,2) DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `final_amount` decimal(10,2) DEFAULT '0.00',
  `payment_method` enum('cash','card','mobile_money','credit') DEFAULT 'cash',
  `payment_status` enum('paid','pending','cancelled') DEFAULT 'paid',
  `sale_status` enum('completed','pending','cancelled','refunded') DEFAULT 'completed',
  `staff_id` int NOT NULL,
  `notes` text,
  `sale_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
)  

CREATE TABLE `EASYSALLES_SALE_ITEMS` (
  `item_id` int NOT NULL,
  `sale_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
)  

CREATE TABLE `EASYSALLES_SHIFTS` (
  `shift_id` int NOT NULL,
  `shift_name` varchar(50) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `color` varchar(20) DEFAULT '#7C3AED',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
)  

CREATE TABLE `EASYSALLES_SHIFT_REQUESTS` (
  `request_id` int NOT NULL,
  `user_id` int NOT NULL,
  `request_type` enum('swap','timeoff','cover','change') NOT NULL,
  `shift_id` int DEFAULT NULL,
  `requested_shift_id` int DEFAULT NULL,
  `requested_user_id` int DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `reason` text,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `admin_notes` text,
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)  

CREATE TABLE `EASYSALLES_SHIFT_SWAPS` (
  `swap_id` int NOT NULL,
  `request_id` int NOT NULL,
  `original_shift_id` int NOT NULL,
  `new_shift_id` int NOT NULL,
  `swapped_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
)  

CREATE TABLE `EASYSALLES_SUPPLIERS` (
  `supplier_id` int NOT NULL,
  `supplier_name` varchar(255) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `products_supplied` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) 

CREATE TABLE `EASYSALLES_USERS` (
  `user_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` tinyint NOT NULL DEFAULT '2',
  `avatar_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `reset_attempts` int DEFAULT '0',
  `last_password_change` datetime DEFAULT NULL,
  `force_password_change` tinyint(1) DEFAULT '0',
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `shift_start` time DEFAULT NULL,
  `shift_end` time DEFAULT NULL,
  `shift_days` varchar(50) DEFAULT NULL COMMENT 'e.g., Mon-Fri, Weekends',
  `salary` decimal(10,2) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `notes` text
)

CREATE TABLE `EASYSALLES_USER_SHIFTS` (
  `user_shift_id` int NOT NULL,
  `user_id` int NOT NULL,
  `shift_id` int NOT NULL,
  `assigned_date` date NOT NULL,
  `status` enum('scheduled','completed','cancelled','absent') DEFAULT 'scheduled',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) 