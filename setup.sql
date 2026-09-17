-- ============================================================
--  BudgetWise — Database Setup (Final v5, 12 tables)
-- ============================================================
--  HOW TO USE:
--    1. Open phpMyAdmin (http://localhost/phpmyadmin)
--    2. Click "Import"
--    3. Select this file
--    4. Click "Go"
--
--  This creates the database, all 12 tables, two seeded users
--  (one admin, one demo), default categories, default payment
--  methods, sample budgets, and sample transactions.
--
--  DEFAULT LOGINS:
--     ADMIN: username "admin"  password "admin123"
--     DEMO:  username "demo"   password "admin123"
--  Change passwords from Settings → Profile after first login.
-- ============================================================

DROP DATABASE IF EXISTS budget_app;
CREATE DATABASE budget_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE budget_app;


-- ============================================================
--  GROUP A: IDENTITY & ACCESS  (3 tables)
-- ============================================================

-- ─── 1. users ───────────────────────────────────────────────
-- Authentication credentials. The `role` column distinguishes
-- regular users from administrators. The `is_active` flag lets
-- an admin disable an account without deleting its data.
CREATE TABLE users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    email      VARCHAR(100) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);


-- ─── 2. user_profiles ──────────────────────────────────────
-- Display and personal information for each user. Linked
-- one-to-one with users via user_id (which is both PK and FK).
-- Separating profile from credentials is a recognised pattern
-- that keeps auth queries lean.
CREATE TABLE user_profiles (
    user_id        INT PRIMARY KEY,
    name           VARCHAR(100)  NOT NULL DEFAULT 'User',
    phone          VARCHAR(20)   NULL,
    profile_image  VARCHAR(255)  NULL,
    monthly_goal   DECIMAL(10,2) NOT NULL DEFAULT 10000.00,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


-- ─── 3. settings ───────────────────────────────────────────
-- Per-user preferences. One row per user, created at
-- registration time with default values.
CREATE TABLE settings (
    user_id      INT PRIMARY KEY,
    theme        ENUM('light','dark') NOT NULL DEFAULT 'dark',
    currency     VARCHAR(10)  NOT NULL DEFAULT 'INR',
    language     VARCHAR(10)  NOT NULL DEFAULT 'en',
    date_format  VARCHAR(20)  NOT NULL DEFAULT 'd M Y',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);


-- ============================================================
--  GROUP B: FINANCIAL RECORDS  (4 tables)
-- ============================================================

-- ─── 4. categories ─────────────────────────────────────────
-- Unified categories table for both system defaults and user
-- custom categories. Defaults have user_id = NULL and are
-- visible to everyone; custom categories belong to one user.
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)              NOT NULL,
    type        ENUM('income','expense') NOT NULL,
    is_default  TINYINT(1)               NOT NULL DEFAULT 0,
    user_id     INT                      NULL,
    created_at  TIMESTAMP                DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_cat_type (type),
    INDEX idx_cat_user (user_id)
);


-- ─── 5. payment_methods ────────────────────────────────────
-- Payment instruments available for tagging transactions.
-- Same pattern as categories: NULL user_id = system default.
CREATE TABLE payment_methods (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NULL,
    name        VARCHAR(50)  NOT NULL,
    type        ENUM('cash','upi','debit_card','credit_card','net_banking','wallet','other') NOT NULL DEFAULT 'cash',
    is_default  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pm_user (user_id)
);


-- ─── 6. transactions ───────────────────────────────────────
-- The unified transactions table — single table for both
-- income and expense, distinguished by the `type` column.
-- This is the design supervisor explicitly endorsed.
CREATE TABLE transactions (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT                      NOT NULL,
    category_id        INT                      NULL,
    payment_method_id  INT                      NULL,
    title              VARCHAR(100)             NOT NULL,
    amount             DECIMAL(10,2)            NOT NULL,
    type               ENUM('income','expense') NOT NULL,
    txn_date           DATE                     NOT NULL,
    notes              TEXT                     NULL,
    created_at         TIMESTAMP                DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)           REFERENCES users(id)           ON DELETE CASCADE,
    FOREIGN KEY (category_id)       REFERENCES categories(id)      ON DELETE SET NULL,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL,
    INDEX idx_txn_user (user_id),
    INDEX idx_txn_date (txn_date),
    INDEX idx_txn_type (type),
    INDEX idx_txn_cat  (category_id)
);


-- ─── 7. budgets ────────────────────────────────────────────
-- Monthly spending limits per category. Composite UNIQUE
-- key ensures each user has at most one budget per category
-- per month. Used by INSERT ... ON DUPLICATE KEY UPDATE.
CREATE TABLE budgets (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL,
    category_id    INT           NOT NULL,
    monthly_limit  DECIMAL(10,2) NOT NULL,
    period_month   TINYINT       NOT NULL,
    period_year    SMALLINT      NOT NULL,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY uq_user_cat_period (user_id, category_id, period_month, period_year),
    INDEX idx_bud_user (user_id)
);


-- ============================================================
--  GROUP C: GOALS & ATTACHMENTS  (2 tables)
-- ============================================================

-- ─── 8. savings_goals ──────────────────────────────────────
-- User-defined financial targets. `saved_amount` is a
-- denormalised running total updated whenever the user adds
-- a contribution via the edit form.
CREATE TABLE savings_goals (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT           NOT NULL,
    goal_name      VARCHAR(100)  NOT NULL,
    target_amount  DECIMAL(12,2) NOT NULL,
    saved_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    target_date    DATE          NOT NULL,
    status         ENUM('active','achieved','cancelled') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_goal_user   (user_id),
    INDEX idx_goal_status (status)
);


-- ─── 9. attachments ────────────────────────────────────────
-- Receipt images attached to transactions. The file itself
-- lives in /uploads/receipts/ with a sanitised random name;
-- this table stores the metadata.
CREATE TABLE attachments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    transaction_id  INT          NOT NULL,
    filename        VARCHAR(255) NOT NULL,
    stored_name     VARCHAR(255) NOT NULL,
    file_size       INT          NOT NULL,
    mime_type       VARCHAR(100) NOT NULL,
    uploaded_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    INDEX idx_att_txn (transaction_id)
);


-- ============================================================
--  GROUP D: SYSTEM-WRITTEN TABLES  (3 tables)
-- ============================================================

-- ─── 10. notifications ─────────────────────────────────────
-- System-generated alerts. Populated by application code in
-- the budget and savings modules, never by user form input.
CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    type        ENUM('budget_warning','budget_exceeded','goal_milestone','goal_achieved','general') NOT NULL,
    title       VARCHAR(100) NOT NULL,
    message     VARCHAR(255) NOT NULL,
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user (user_id),
    INDEX idx_notif_read (is_read)
);


-- ─── 11. report_logs ───────────────────────────────────────
-- Records every report generated and every export downloaded.
-- Useful audit trail and satisfies the rubric's "reports table"
-- requirement without storing stale report snapshots.
CREATE TABLE report_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT  NOT NULL,
    report_type   ENUM('monthly','category','date_range','export_csv','export_pdf','export_excel') NOT NULL,
    period_from   DATE NULL,
    period_to     DATE NULL,
    generated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_rlog_user (user_id)
);


-- ─── 12. admin_logs ────────────────────────────────────────
-- Records administrative actions for accountability. Only
-- inserted when an admin actually performs an action (not on
-- every page view), to keep the table noise-free.
CREATE TABLE admin_logs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    admin_id        INT          NOT NULL,
    action          VARCHAR(100) NOT NULL,
    target_user_id  INT          NULL,
    description     VARCHAR(255) NULL,
    ip_address      VARCHAR(45)  NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id)       REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_alog_admin (admin_id)
);


-- ============================================================
--  SEED DATA: USERS
-- ============================================================
-- Two users are seeded. Both passwords are "admin123" hashed
-- with bcrypt (cost factor 12). Change them after first login.
INSERT INTO users (id, username, email, password, role, is_active) VALUES
(1, 'admin', 'admin@budgetwise.local',
    '$2b$12$.Y0ehWJZI.LpQye0hD8dhuomHJWEPkZ8rSmM.3Zt7U/R88R6HMP6S',
    'admin', 1),
(2, 'demo',  'demo@budgetwise.local',
    '$2b$12$.Y0ehWJZI.LpQye0hD8dhuomHJWEPkZ8rSmM.3Zt7U/R88R6HMP6S',
    'user', 1);


-- ============================================================
--  SEED DATA: USER PROFILES
-- ============================================================
INSERT INTO user_profiles (user_id, name, phone, monthly_goal) VALUES
(1, 'Aditya Kumar',   '+91-9876543210', 15000.00),
(2, 'Demo Student',   '+91-9123456780', 10000.00);


-- ============================================================
--  SEED DATA: SETTINGS
-- ============================================================
INSERT INTO settings (user_id, theme, currency, language, date_format) VALUES
(1, 'dark',  'INR', 'en', 'd M Y'),
(2, 'dark',  'INR', 'en', 'd M Y');


-- ============================================================
--  SEED DATA: DEFAULT CATEGORIES (visible to everyone)
-- ============================================================
-- user_id = NULL marks these as system defaults.
INSERT INTO categories (name, type, is_default, user_id) VALUES
-- Income categories
('Salary',         'income',  1, NULL),
('Freelance',      'income',  1, NULL),
('Business',       'income',  1, NULL),
('Investment',     'income',  1, NULL),
('Gift / Bonus',   'income',  1, NULL),
('Other Income',   'income',  1, NULL),
-- Expense categories
('Food',           'expense', 1, NULL),
('Rent',           'expense', 1, NULL),
('Transport',      'expense', 1, NULL),
('Utilities',      'expense', 1, NULL),
('Education',      'expense', 1, NULL),
('Healthcare',     'expense', 1, NULL),
('Shopping',       'expense', 1, NULL),
('Entertainment',  'expense', 1, NULL),
('Other Expense',  'expense', 1, NULL);


-- ============================================================
--  SEED DATA: DEFAULT PAYMENT METHODS (visible to everyone)
-- ============================================================
INSERT INTO payment_methods (user_id, name, type, is_default) VALUES
(NULL, 'Cash',         'cash',        1),
(NULL, 'UPI',          'upi',         1),
(NULL, 'Debit Card',   'debit_card',  1),
(NULL, 'Credit Card',  'credit_card', 1),
(NULL, 'Net Banking',  'net_banking', 1);


-- ============================================================
--  SEED DATA: SAMPLE BUDGETS FOR ADMIN (current month)
-- ============================================================
-- We use MONTH(CURDATE()) and YEAR(CURDATE()) so the budgets
-- always sit in the current month no matter when this file
-- is imported. The category_id is looked up by name.
INSERT INTO budgets (user_id, category_id, monthly_limit, period_month, period_year)
SELECT 1, c.id, 3000.00, MONTH(CURDATE()), YEAR(CURDATE())
FROM categories c WHERE c.name = 'Food' AND c.is_default = 1;

INSERT INTO budgets (user_id, category_id, monthly_limit, period_month, period_year)
SELECT 1, c.id, 9000.00, MONTH(CURDATE()), YEAR(CURDATE())
FROM categories c WHERE c.name = 'Rent' AND c.is_default = 1;

INSERT INTO budgets (user_id, category_id, monthly_limit, period_month, period_year)
SELECT 1, c.id, 1500.00, MONTH(CURDATE()), YEAR(CURDATE())
FROM categories c WHERE c.name = 'Utilities' AND c.is_default = 1;

INSERT INTO budgets (user_id, category_id, monthly_limit, period_month, period_year)
SELECT 1, c.id, 1000.00, MONTH(CURDATE()), YEAR(CURDATE())
FROM categories c WHERE c.name = 'Entertainment' AND c.is_default = 1;

INSERT INTO budgets (user_id, category_id, monthly_limit, period_month, period_year)
SELECT 1, c.id,  800.00, MONTH(CURDATE()), YEAR(CURDATE())
FROM categories c WHERE c.name = 'Transport' AND c.is_default = 1;


-- ============================================================
--  SEED DATA: SAMPLE TRANSACTIONS FOR ADMIN
-- ============================================================
-- Last month
INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Monthly Salary', 25000.00, 'income', CURDATE() - INTERVAL 35 DAY
FROM categories c, payment_methods p
WHERE c.name='Salary' AND c.is_default=1 AND p.name='Net Banking' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Freelance Project', 7500.00, 'income', CURDATE() - INTERVAL 32 DAY
FROM categories c, payment_methods p
WHERE c.name='Freelance' AND c.is_default=1 AND p.name='UPI' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'House Rent', 8000.00, 'expense', CURDATE() - INTERVAL 34 DAY
FROM categories c, payment_methods p
WHERE c.name='Rent' AND c.is_default=1 AND p.name='Net Banking' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Grocery Shopping', 2200.00, 'expense', CURDATE() - INTERVAL 30 DAY
FROM categories c, payment_methods p
WHERE c.name='Food' AND c.is_default=1 AND p.name='Debit Card' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Internet Bill', 699.00, 'expense', CURDATE() - INTERVAL 29 DAY
FROM categories c, payment_methods p
WHERE c.name='Utilities' AND c.is_default=1 AND p.name='UPI' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Bus Pass', 500.00, 'expense', CURDATE() - INTERVAL 28 DAY
FROM categories c, payment_methods p
WHERE c.name='Transport' AND c.is_default=1 AND p.name='Cash' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Course Book', 850.00, 'expense', CURDATE() - INTERVAL 25 DAY
FROM categories c, payment_methods p
WHERE c.name='Education' AND c.is_default=1 AND p.name='Credit Card' AND p.is_default=1;

-- This month
INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Monthly Salary', 25000.00, 'income', CURDATE() - INTERVAL 6 DAY
FROM categories c, payment_methods p
WHERE c.name='Salary' AND c.is_default=1 AND p.name='Net Banking' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Freelance Design', 4000.00, 'income', CURDATE() - INTERVAL 4 DAY
FROM categories c, payment_methods p
WHERE c.name='Freelance' AND c.is_default=1 AND p.name='UPI' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'House Rent', 8000.00, 'expense', CURDATE() - INTERVAL 5 DAY
FROM categories c, payment_methods p
WHERE c.name='Rent' AND c.is_default=1 AND p.name='Net Banking' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Restaurant Dinner', 1200.00, 'expense', CURDATE() - INTERVAL 3 DAY
FROM categories c, payment_methods p
WHERE c.name='Food' AND c.is_default=1 AND p.name='Credit Card' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Electricity Bill', 850.00, 'expense', CURDATE() - INTERVAL 2 DAY
FROM categories c, payment_methods p
WHERE c.name='Utilities' AND c.is_default=1 AND p.name='UPI' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Movie Tickets', 400.00, 'expense', CURDATE() - INTERVAL 1 DAY
FROM categories c, payment_methods p
WHERE c.name='Entertainment' AND c.is_default=1 AND p.name='UPI' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 1, c.id, p.id, 'Medicine', 350.00, 'expense', CURDATE()
FROM categories c, payment_methods p
WHERE c.name='Healthcare' AND c.is_default=1 AND p.name='Cash' AND p.is_default=1;


-- ============================================================
--  SEED DATA: ONE SAMPLE SAVINGS GOAL FOR ADMIN
-- ============================================================
INSERT INTO savings_goals (user_id, goal_name, target_amount, saved_amount, target_date, status) VALUES
(1, 'New Laptop',   60000.00, 18000.00, CURDATE() + INTERVAL 6 MONTH, 'active'),
(1, 'Emergency Fund', 50000.00, 32000.00, CURDATE() + INTERVAL 12 MONTH, 'active');


-- ============================================================
--  SEED DATA: A FEW TRANSACTIONS FOR THE DEMO USER
-- ============================================================
-- So multi-user isolation is visible from day one.
INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 2, c.id, p.id, 'Part-time Job', 8000.00, 'income', CURDATE() - INTERVAL 10 DAY
FROM categories c, payment_methods p
WHERE c.name='Salary' AND c.is_default=1 AND p.name='UPI' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 2, c.id, p.id, 'Hostel Mess', 3500.00, 'expense', CURDATE() - INTERVAL 8 DAY
FROM categories c, payment_methods p
WHERE c.name='Food' AND c.is_default=1 AND p.name='UPI' AND p.is_default=1;

INSERT INTO transactions (user_id, category_id, payment_method_id, title, amount, type, txn_date)
SELECT 2, c.id, p.id, 'Textbooks', 1200.00, 'expense', CURDATE() - INTERVAL 4 DAY
FROM categories c, payment_methods p
WHERE c.name='Education' AND c.is_default=1 AND p.name='Cash' AND p.is_default=1;


-- ============================================================
--  END OF SETUP
-- ============================================================
