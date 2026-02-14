<?php
// db_setup_inventory.php - (Inventory System V2.1 Setup - With FK Checks)
require 'config.php';

// Turn on error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<div style='font-family: monospace; background: #0a0a0a; color: #eee; padding: 20px; border-radius: 10px; line-height: 1.6;'>";
echo "<h1><span style='color: #d4af37;'>🚀</span> Inventory Database Setup V2.1</h1>";

// Function to execute a query and print status
function run_query($conn, $sql, $message) {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: #2ecc71;'>✅ Success: {$message}</p>";
    } else {
        echo "<p style='color: #e74c3c;'>❌ Error: {$message} -> " . $conn->error . "</p>";
    }
}

// --- 0. Disable Foreign Key Checks ---
echo "<h2>0. Temporarily disabling foreign key checks...</h2>";
run_query($conn, "SET FOREIGN_KEY_CHECKS=0;", "Foreign key checks disabled.");

// --- 1. Drop old tables if they exist ---
echo "<h2>1. Cleaning up old tables...</h2>";
run_query($conn, "DROP TABLE IF EXISTS `inventory_movements`", "Dropped `inventory_movements`");
run_query($conn, "DROP TABLE IF EXISTS `inventory_stock`", "Dropped `inventory_stock`");
run_query($conn, "DROP TABLE IF EXISTS `inventory_items`", "Dropped `inventory_items`");

// --- 2. Create New Tables for V2.0 ---
echo "<h2>2. Creating new schema...</h2>";

// Table: warehouses - To store different physical or logical stock locations
$sql_warehouses = "CREATE TABLE IF NOT EXISTS `warehouses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `location` TEXT,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_query($conn, $sql_warehouses, "Table `warehouses` created.");

// Table: products - Core product information
$sql_products = "CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `sku` VARCHAR(100) UNIQUE,
    `description` TEXT,
    `category` VARCHAR(100),
    `sale_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `purchase_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `low_stock_threshold` INT NOT NULL DEFAULT 10,
    `image_path` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_query($conn, $sql_products, "Table `products` created.");

// Table: product_stock - Junction table to track stock quantity per product per warehouse
$sql_product_stock = "CREATE TABLE IF NOT EXISTS `product_stock` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `warehouse_id` INT NOT NULL,
    `quantity` DECIMAL(10, 2) NOT NULL,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `product_warehouse` (`product_id`, `warehouse_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_query($conn, $sql_product_stock, "Table `product_stock` created.");

// Table: stock_movements - Log of all stock changes for auditing and history
$sql_stock_movements = "CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `warehouse_id` INT NOT NULL,
    `quantity_change` DECIMAL(10, 2) NOT NULL COMMENT 'Positive for IN, Negative for OUT',
    `type` VARCHAR(50) NOT NULL COMMENT 'e.g., sale, purchase, transfer, adjustment',
    `reference_id` INT COMMENT 'e.g., job_id, purchase_order_id',
    `notes` TEXT,
    `user_id` INT,
    `movement_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
run_query($conn, $sql_stock_movements, "Table `stock_movements` created.");

// --- 3. Add a default warehouse if none exist ---
echo "<h2>3. Initializing data...</h2>";
$check_wh = "SELECT id FROM `warehouses` LIMIT 1";
$res = $conn->query($check_wh);
if ($res->num_rows == 0) {
    $sql_default_wh = "INSERT INTO `warehouses` (name, location) VALUES ('المخزن الرئيسي', 'المقر الرئيسي للشركة')";
    run_query($conn, $sql_default_wh, "Default warehouse created.");
} else {
    echo "<p style='color: #f1c40f;'>- Warehouses already exist. Skipping creation of default warehouse.</p>";
}

// --- 4. Re-enable Foreign Key Checks ---
echo "<h2>4. Re-enabling foreign key checks...</h2>";
run_query($conn, "SET FOREIGN_KEY_CHECKS=1;", "Foreign key checks re-enabled.");

// --- Final Message ---
echo "<hr style='border-color: #333;'>";
echo "<p style='font-weight: bold; color: #fff;'>Setup complete! You can now use the new inventory system.</p>";
echo "<p style='font-weight: bold; color: #e74c3c;'>IMPORTANT: Please delete this file (`db_setup_inventory.php`) from the server immediately for security reasons.</p>";
echo "<a href='inventory.php' style='color: #d4af37; font-size: 1.2em; text-decoration: none;'>&larr; Go back to Inventory</a>";
echo "</div>";

$conn->close();
?>
