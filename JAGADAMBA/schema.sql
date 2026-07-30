-- Sahara Electrical - Database Schema Definition
-- Character Set: UTF8MB4

-- NOTE FOR INFINITYFREE:
-- 1. Do NOT create or use database queries here. InfinityFree creates the database for you.
-- 2. Open phpMyAdmin on InfinityFree, click on your pre-created database name (e.g. if0_42305887_db) in the sidebar.
-- 3. Go to the "SQL" or "Import" tab, and run the SQL below.

-- 1. Raw Material Master Table
CREATE TABLE IF NOT EXISTS `raw_material` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `m_code` VARCHAR(50) UNIQUE NOT NULL,
  `m_description` TEXT,
  `head` VARCHAR(100),
  `bal_qty` DECIMAL(10,2) DEFAULT 0.00,
  `unit` VARCHAR(20),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Transporter Master Table
CREATE TABLE IF NOT EXISTS `transport_master` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `transport_name` VARCHAR(100) NOT NULL,
  `vech_no` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bill of Material (BOM) Table
CREATE TABLE IF NOT EXISTS `bom` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `wound_code` VARCHAR(50) NOT NULL,
  `mac_no` VARCHAR(50) NOT NULL,
  `rm_code` VARCHAR(50) NOT NULL,
  `m_description` TEXT,
  `req_qty` DECIMAL(10,2) NOT NULL,
  `material_type` VARCHAR(50),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`wound_code`),
  INDEX (`rm_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. PO Master Table
CREATE TABLE IF NOT EXISTS `po_master` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `pono` VARCHAR(50) NOT NULL,
  `po_date` DATE,
  `item_no` VARCHAR(50),
  `wound_code` VARCHAR(50),
  `description` TEXT,
  `drg_no` VARCHAR(50),
  `rate` DECIMAL(10,2),
  `unit` VARCHAR(20),
  `party_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`pono`),
  INDEX (`wound_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. PO Master Copy Table (Archive of PO data)
CREATE TABLE IF NOT EXISTS `po_master_copy` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `pono` VARCHAR(50) NOT NULL,
  `po_date` DATE,
  `item_no` VARCHAR(50),
  `wound_code` VARCHAR(50),
  `description` TEXT,
  `drg_no` VARCHAR(50),
  `rate` DECIMAL(10,2),
  `unit` VARCHAR(20),
  `party_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`pono`),
  INDEX (`wound_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Inward Transactions Table
CREATE TABLE IF NOT EXISTS `inward_transaction` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `ch_no` VARCHAR(50) NOT NULL,
  `ch_date` DATE NOT NULL,
  `m_code` VARCHAR(50) NOT NULL,
  `m_description` TEXT,
  `head` VARCHAR(100),
  `in_qty` DECIMAL(10,2) NOT NULL,
  `bal_qty` DECIMAL(10,2) NOT NULL,
  `unit` VARCHAR(20),
  `material_type` VARCHAR(50),
  `remark` TEXT,
  `challan_type` VARCHAR(50),
  `mc_no` VARCHAR(50),
  `party_id` INT NULL,
  `part_no` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`ch_no`),
  INDEX (`m_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Pending/Remaining Machines Table
CREATE TABLE IF NOT EXISTS `remaining_machines` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `ch_no` VARCHAR(50) NOT NULL,
  `ch_date` DATE NOT NULL,
  `machine_no` VARCHAR(50) NOT NULL,
  `status` ENUM('pending','invoiced','completed') DEFAULT 'pending',
  `party_id` INT NULL,
  `part_no` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`ch_no`),
  INDEX (`machine_no`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tax Invoice Table
CREATE TABLE IF NOT EXISTS `tax_invoice` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `inv_no` VARCHAR(50) NOT NULL,
  `inv_date` DATE NOT NULL,
  `po_no` VARCHAR(50),
  `po_date` DATE,
  `asn_no` VARCHAR(50) DEFAULT '9988',
  `vehicle_no` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `item_sr_no` VARCHAR(50),
  `drg_no` VARCHAR(50) DEFAULT '9988',
  `hsn_code` VARCHAR(50) DEFAULT '9988',
  `qty` DECIMAL(10,2) NOT NULL,
  `uom` VARCHAR(20) DEFAULT 'NOS',
  `rate` DECIMAL(10,2),
  `amount` DECIMAL(10,2),
  `sub_total` DECIMAL(10,2),
  `cgst` DECIMAL(10,2),
  `sgst` DECIMAL(10,2),
  `net_total` DECIMAL(10,2),
  `wound_code` VARCHAR(50),
  `party_id` INT NULL,
  `part_no` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`inv_no`),
  INDEX (`wound_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Invoice Machines Map Table
CREATE TABLE IF NOT EXISTS `invoice_machines` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `inv_no` VARCHAR(50) NOT NULL,
  `machine_no` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`inv_no`),
  INDEX (`machine_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Invoice Consumption Log Table (Tracks FIFO raw material consumption)
CREATE TABLE IF NOT EXISTS `invoice_consumption_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `inv_no` VARCHAR(50) NOT NULL,
  `inward_id` INT NOT NULL,
  `m_code` VARCHAR(50) NOT NULL,
  `qty_used` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`inv_no`),
  INDEX (`inward_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Workers Master Table
CREATE TABLE IF NOT EXISTS `workers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `mobile_no` VARCHAR(20) NOT NULL,
  `emp_no` VARCHAR(50) UNIQUE NOT NULL,
  `designation` VARCHAR(50) NOT NULL DEFAULT 'experience',
  `hourly_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `doc_id_card` VARCHAR(255) NULL,
  `doc_tax_card` VARCHAR(255) NULL,
  `doc_passbook` VARCHAR(255) NULL,
  `doc_photo` VARCHAR(255) NULL,
  `doc_other` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Machines Master Table
CREATE TABLE IF NOT EXISTS `machines` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `machine_no` VARCHAR(50) UNIQUE NOT NULL,
  `rate` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Daily Worker Work Table
CREATE TABLE IF NOT EXISTS `daily_work` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `worker_id` INT NOT NULL,
  `machine_id` INT NULL,
  `work_date` DATE NOT NULL,
  `qty` DECIMAL(10,2) NULL,
  `in_time` TIME NULL,
  `out_time` TIME NULL,
  `construction_no` VARCHAR(50) NULL,
  `attendance` ENUM('present', 'absent') DEFAULT 'present',
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Parties Table
CREATE TABLE IF NOT EXISTS `parties` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `party_name` VARCHAR(100) UNIQUE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Challan Inward Table (non-payment, editable inward records)
CREATE TABLE IF NOT EXISTS `challan_inward` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `ch_no` VARCHAR(50) NOT NULL,
  `ch_date` DATE NOT NULL,
  `m_code` VARCHAR(50) NOT NULL,
  `m_description` TEXT,
  `vehicle_no` VARCHAR(50),
  `qty` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`ch_no`),
  INDEX (`m_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. NFP Outward Challan Table (independent, no connection with other tables)
CREATE TABLE IF NOT EXISTS `nfp_outward` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `challan_no` VARCHAR(50) NOT NULL,
  `challan_date` DATE NOT NULL,
  `party_id` INT NULL,
  `vehicle_no` VARCHAR(50),
  `sr_no` INT,
  `wound_code` VARCHAR(50),
  `description` TEXT,
  `m_code` VARCHAR(50),
  `hsn_code` VARCHAR(50) DEFAULT '9988',
  `qty` DECIMAL(10,2) NOT NULL,
  `uom` VARCHAR(20) DEFAULT 'NOS',
  `rate` DECIMAL(10,2) DEFAULT 0.00,
  `amount` DECIMAL(10,2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`challan_no`),
  INDEX (`m_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
