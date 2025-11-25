-- Dropping old tables and creating fresh schema with proper sample data
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS remember_tokens;
DROP TABLE IF EXISTS users;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
);

-- Create remember_tokens table for "Remember Me" functionality
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- Create categories table for transaction categorization
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    icon VARCHAR(50),
    color VARCHAR(7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    UNIQUE KEY unique_user_category (user_id, name)
);

-- Create transactions table for all income/expense records
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT,
    amount DECIMAL(10, 2) NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    description VARCHAR(500),
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_date (transaction_date),
    INDEX idx_type (type),
    INDEX idx_user_date (user_id, transaction_date)
);

-- Create notifications table for real-time alerts
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_user_unread (user_id, is_read)
);

-- Create chat messages table for messaging
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    attachment_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_user_sender (user_id, sender_id)
);

-- Insert admin user (password: admin123)
INSERT INTO users (username, email, password, is_active) VALUES 
('admin', 'admin@flowstack.com', '$2y$10$YIjlrHyWw9y9c4F.xE6F9eKwK5Zn5c5F7c5F7c5F7c5F7c5F7c5F7c', 1);

-- Insert categories
INSERT INTO categories (user_id, name, icon, color) VALUES
(1, 'Food & Dining', 'bi-cup-straw', '#FF6B6B'),
(1, 'Transportation', 'bi-car-front', '#4ECDC4'),
(1, 'Shopping', 'bi-bag-check', '#45B7D1'),
(1, 'Entertainment', 'bi-film', '#96CEB4'),
(1, 'Utilities', 'bi-lightning', '#FFEAA7'),
(1, 'Salary', 'bi-cash-coin', '#00CC88'),
(1, 'Freelance', 'bi-briefcase', '#0066FF'),
(1, 'Investment', 'bi-graph-up', '#6C5CE7');

-- Insert transactions
INSERT INTO transactions (user_id, category_id, amount, type, description, transaction_date) VALUES
(1, 1, 1500.00, 'expense', 'Lunch at restaurant', CURDATE()),
(1, 2, 800.00, 'expense', 'Fuel for car', DATE_SUB(CURDATE(), INTERVAL 1 DAY)),
(1, 6, 50000.00, 'income', 'Monthly salary', DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(1, 5, 3500.00, 'expense', 'Electricity bill', DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
(1, 3, 5000.00, 'expense', 'Shopping at mall', DATE_SUB(CURDATE(), INTERVAL 10 DAY)),
(1, 1, 900.00, 'expense', 'Coffee and snacks', DATE_SUB(CURDATE(), INTERVAL 12 DAY)),
(1, 4, 2000.00, 'expense', 'Movie tickets', DATE_SUB(CURDATE(), INTERVAL 15 DAY)),
(1, 7, 5000.00, 'income', 'Freelance project', DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
(1, 5, 2200.00, 'expense', 'Internet bill', DATE_SUB(CURDATE(), INTERVAL 20 DAY)),
(1, 2, 1200.00, 'expense', 'Car maintenance', DATE_SUB(CURDATE(), INTERVAL 25 DAY));

-- Insert notifications
INSERT INTO notifications (user_id, title, message, type, is_read) VALUES
(1, 'Budget Alert', 'You have exceeded your food budget for this month', 'warning', 0),
(1, 'Income Received', 'Your salary of KES 50,000.00 has been credited', 'success', 0),
(1, 'Reminder', 'Upcoming electricity bill due on 25th', 'info', 0),
(1, 'Expense Alert', 'High expense detected - Shopping at mall: KES 5,000.00', 'warning', 1),
(1, 'Freelance Income', 'New freelance project income: KES 5,000.00', 'success', 1);

-- Insert chat messages
INSERT INTO chat_messages (user_id, sender_id, message, created_at) VALUES
(1, 1, 'Hello! I am your financial assistant. How can I help you today?', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(1, 1, 'You can add expenses, income, or view your financial reports.', DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
(1, 1, 'Your current balance is KES 44,900.00', DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
(1, 1, 'Would you like to create a budget or add a new transaction?', DATE_SUB(NOW(), INTERVAL 10 MINUTE));
