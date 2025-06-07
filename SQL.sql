-- =============================================
-- CREATE DATABASE
-- =============================================
CREATE DATABASE IF NOT EXISTS fitzone 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE fitzone;

-- =============================================
-- TABLES WITH ENHANCED SECURITY
-- =============================================

-- Admin Table with Additional Security Fields
CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    token_expires DATETIME NULL,
    login_attempts INT DEFAULT 0,
    last_attempt DATETIME NULL,
    status ENUM('active', 'inactive', 'locked') DEFAULT 'active',
    two_factor_secret VARCHAR(255) NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Create users table (if it doesn't exist) with profile_image column included
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    role ENUM('customer', 'staff', 'admin') DEFAULT 'customer',
    status ENUM('active', 'inactive', 'locked') DEFAULT 'active',
    profile_image VARCHAR(255) NULL COMMENT 'Path to user profile image',
    login_attempts INT DEFAULT 0,
    last_attempt DATETIME NULL,
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role (role),
    INDEX idx_user_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS uploaded_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL,
    user_id INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_file_path (file_path),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- Finally add the foreign key
ALTER TABLE uploaded_files
ADD CONSTRAINT fk_uploaded_files_user
FOREIGN KEY (user_id) REFERENCES users(id)
ON DELETE SET NULL;

-- Password History Table
CREATE TABLE IF NOT EXISTS password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    password VARCHAR(255) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Trainers Table
CREATE TABLE IF NOT EXISTS trainers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE,
    name VARCHAR(100) NOT NULL,
    specialty VARCHAR(100),
    certification VARCHAR(100),
    bio TEXT,
    hourly_rate DECIMAL(10,2),
    is_active BOOLEAN DEFAULT TRUE,
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Classes Table
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    schedule DATETIME NOT NULL,
    duration_minutes INT DEFAULT 60,
    trainer_id INT,
    capacity INT DEFAULT 20,
    booked INT DEFAULT 0,
    price DECIMAL(10,2) DEFAULT 15.00,
    class_type ENUM('regular', 'advanced', 'premium') DEFAULT 'regular',
    status ENUM('scheduled', 'cancelled', 'completed') DEFAULT 'scheduled',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_schedule (schedule),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Queries Table
CREATE TABLE IF NOT EXISTS queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    response TEXT,
    status ENUM('pending', 'in_progress', 'resolved') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    resolved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status_priority (status, priority)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_type ENUM('error', 'warning', 'info', 'success', 'debug') NOT NULL,
    message TEXT NOT NULL,
    context TEXT,
    user_id VARCHAR(100),
    ip_address VARCHAR(45),
    log_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_type (log_type),
    INDEX idx_log_time (log_time)
) ENGINE=InnoDB;


-- Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    class_id INT,
    date DATETIME NOT NULL,
    amount_paid DECIMAL(10,2),
    payment_method ENUM('cash', 'card', 'online') DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
    status ENUM('booked', 'cancelled', 'attended') DEFAULT 'booked',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, date),
    INDEX idx_class_date (class_id, date)
) ENGINE=InnoDB;

-- Reviews Table
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    class_id INT,
    trainer_id INT,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    review TEXT NOT NULL,
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL,
    INDEX idx_rating (rating),
    INDEX idx_approved (is_approved)
) ENGINE=InnoDB;

-- Gym Information Table
CREATE TABLE IF NOT EXISTS gym_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gym_name VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    zip_code VARCHAR(20),
    phone VARCHAR(20),
    email VARCHAR(255),
    opening_hours TEXT,
    facebook_url VARCHAR(255),
    twitter_url VARCHAR(255),
    instagram_url VARCHAR(255),
    youtube_url VARCHAR(255),
    logo_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Staff Schedule Table
CREATE TABLE IF NOT EXISTS staff_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_recurring BOOLEAN DEFAULT TRUE,
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_staff_schedule (staff_id, day_of_week)
) ENGINE=InnoDB;

-- Login History Table
CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255),
    was_successful BOOLEAN NOT NULL,
    failure_reason VARCHAR(100) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_logins (user_id),
    INDEX idx_login_time (login_time)
) ENGINE=InnoDB;

-- Login Attempts Table
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    successful BOOLEAN,
    reason VARCHAR(255),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Remember Tokens Table
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- Password Resets Table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_password_reset_token (token),
    INDEX idx_password_reset_email (email)
) ENGINE=InnoDB;

-- Two-Factor Authentication Table
CREATE TABLE IF NOT EXISTS two_factor_auth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    secret VARCHAR(255) NOT NULL,
    recovery_codes TEXT,
    is_enabled BOOLEAN DEFAULT FALSE,
    last_used_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_two_factor_user (user_id)
) ENGINE=InnoDB;

UPDATE users 
SET profile_image = 'uploads/profiles/default_male.png' 
WHERE gender = 'male' AND profile_image IS NULL;

UPDATE users 
SET profile_image = 'uploads/profiles/default_female.png' 
WHERE gender = 'female' AND profile_image IS NULL;

UPDATE users 
SET profile_image = 'uploads/profiles/default_other.png' 
WHERE (gender = 'other' OR gender IS NULL) AND profile_image IS NULL;

-- =============================================
-- INITIAL DATA
-- =============================================

-- Insert Admin Account with Enhanced Security
INSERT INTO admin (name, email, password, status) VALUES (
    'System Administrator',
    'admin@fitzone.lk',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- admin1234
    'active'
);

-- Insert Base Users
INSERT INTO users (name, email, password, role, status) VALUES
    ('Staff Member', 'staff@fitzone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active'),
    ('Admin User', 'admin@fitzone.lk', '$2y$10$ZnUzE7VbwcMU6thUpVYYiOQyA3h2C.PHNd8.xcakC3xl3WjPKQ5r.', 'admin', 'active'),
    ('Regular Member', 'member@fitzone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'active');

-- Insert Initial Trainer
INSERT INTO trainers (user_id, name, specialty, bio)
VALUES ((SELECT id FROM users WHERE email = 'staff@fitzone.com'), 
        'John Doe', 
        'Cardio', 
        'Certified personal trainer with 10 years experience');

-- =============================================
-- ADD 5 REQUESTED TRAINERS
-- =============================================

-- Trainer 1: Harshana
INSERT INTO users (name, email, password, role, status) VALUES
    ('Harshana Silva', 'harshana@fitzone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active');

INSERT INTO trainers (user_id, name, specialty, certification, bio, hourly_rate) VALUES
    (LAST_INSERT_ID(), 'Harshana Silva', 'Weight Training', 'NASM Certified', 'Specialized in strength training and bodybuilding with 8 years experience', 35.00);

-- Trainer 2: Sampath
INSERT INTO users (name, email, password, role, status) VALUES
    ('Sampath Bandara', 'sampath@fitzone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active');

INSERT INTO trainers (user_id, name, specialty, certification, bio, hourly_rate) VALUES
    (LAST_INSERT_ID(), 'Sampath Bandara', 'Functional Fitness', 'ACE Certified', 'Expert in functional movement patterns and injury prevention', 30.00);

-- Trainer 3: Kasun
INSERT INTO users (name, email, password, role, status) VALUES
    ('Kasun Perera', 'kasun@fitzone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active');

INSERT INTO trainers (user_id, name, specialty, certification, bio, hourly_rate) VALUES
    (LAST_INSERT_ID(), 'Kasun Perera', 'CrossFit', 'CrossFit Level 2 Trainer', 'CrossFit coach with competition experience and nutrition expertise', 40.00);

-- Trainer 4: Perera
INSERT INTO users (name, email, password, role, status) VALUES
    ('Perera Fernando', 'perera@fitzone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active');

INSERT INTO trainers (user_id, name, specialty, certification, bio, hourly_rate) VALUES
    (LAST_INSERT_ID(), 'Perera Fernando', 'Yoga & Mobility', 'RYT 500 Certified', 'Yoga instructor focusing on mobility and stress reduction', 25.00);

-- Trainer 5: Rashmi
INSERT INTO users (name, email, password, role, status) VALUES
    ('Rashmi Wijesekara', 'rashmi@fitzone.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'active');

INSERT INTO trainers (user_id, name, specialty, certification, bio, hourly_rate) VALUES
    (LAST_INSERT_ID(), 'Rashmi Wijesekara', 'Pilates & Core', 'Polestar Pilates Certified', 'Specialist in core strengthening and postural alignment', 28.00);

-- =============================================
-- SAMPLE CLASSES
-- =============================================

INSERT INTO classes (name, description, schedule, trainer_id, capacity, price, class_type, created_by) VALUES
('Morning Yoga', 'Beginner-friendly yoga class', DATE_ADD(NOW(), INTERVAL 1 DAY), 1, 20, 15.00, 'regular', 1),
('Advanced HIIT', 'High-intensity interval training', DATE_ADD(NOW(), INTERVAL 2 DAY), 1, 15, 25.00, 'advanced', 1),
('Personal Training', 'One-on-one coaching session', DATE_ADD(NOW(), INTERVAL 3 DAY), 1, 1, 50.00, 'premium', 1),
('Power Lifting', 'Build strength with proper technique', DATE_ADD(NOW(), INTERVAL 4 DAY), 2, 10, 30.00, 'advanced', 1),
('Functional Movement', 'Improve daily movement patterns', DATE_ADD(NOW(), INTERVAL 5 DAY), 3, 15, 25.00, 'regular', 1),
('CrossFit WOD', 'Daily workout of the day', DATE_ADD(NOW(), INTERVAL 6 DAY), 4, 12, 35.00, 'advanced', 1),
('Yoga Flow', 'Vinyasa style yoga class', DATE_ADD(NOW(), INTERVAL 7 DAY), 5, 20, 20.00, 'regular', 1),
('Core Conditioning', 'Strengthen your core muscles', DATE_ADD(NOW(), INTERVAL 8 DAY), 6, 15, 22.00, 'regular', 1);

-- =============================================
-- GYM INFORMATION
-- =============================================

INSERT INTO gym_info (gym_name, address, city, phone, email, opening_hours) VALUES (
    'FitZone Fitness',
    '123 Fitness Street, Kurunagala',
    'Colombo',
    '+94 76 902 0829',
    'info@fitzone.com',
    'Monday-Friday: 6:00 AM - 10:00 PM\nSaturday-Sunday: 8:00 AM - 8:00 PM'
);

-- =============================================
-- ENHANCED STORED PROCEDURES
-- =============================================

DELIMITER //

-- Enhanced Admin Login Procedure
-- Enhanced Admin Login Procedure
CREATE PROCEDURE sp_admin_login(
    IN p_email VARCHAR(100),
    IN p_password VARCHAR(255)
) -- This closing parenthesis was missing
BEGIN
    DECLARE v_admin_id INT;
    DECLARE v_password_hash VARCHAR(255);
    DECLARE v_status VARCHAR(20);
    DECLARE v_attempts INT;
    DECLARE v_last_attempt DATETIME;
    
    SELECT id, password, status, login_attempts, last_attempt
    INTO v_admin_id, v_password_hash, v_status, v_attempts, v_last_attempt
    FROM admin 
    WHERE email = p_email;
    
    IF v_admin_id IS NULL THEN
        -- Log failed attempt
        INSERT INTO login_attempts (email, successful, reason)
        VALUES (p_email, FALSE, 'Admin account not found');
        
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Admin account not found';
    ELSEIF v_status = 'locked' THEN
        -- Check if lock should be expired (30 minutes)
        IF TIMESTAMPDIFF(MINUTE, v_last_attempt, NOW()) > 30 THEN
            UPDATE admin SET status = 'active', login_attempts = 0 WHERE id = v_admin_id;
        ELSE
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account locked. Try again later or contact support';
        END IF;
    ELSEIF v_status = 'inactive' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account inactive. Contact support';
    ELSE
        IF password_verify(p_password, v_password_hash) THEN
            -- Successful login
            UPDATE admin 
            SET login_attempts = 0,
                last_attempt = NULL,
                last_login = NOW()
            WHERE id = v_admin_id;
            
            -- Log successful attempt
            INSERT INTO login_attempts (email, successful)
            VALUES (p_email, TRUE);
            
            SELECT v_admin_id AS admin_id, 'Login successful' AS message;
        ELSE
            -- Failed attempt
            SET v_attempts = v_attempts + 1;
            
            UPDATE admin 
            SET login_attempts = v_attempts,
                last_attempt = NOW()
            WHERE id = v_admin_id;
            
            -- Lock account if too many attempts
            IF v_attempts >= 5 THEN
                UPDATE admin SET status = 'locked' WHERE id = v_admin_id;
                
                INSERT INTO login_attempts (email, successful, reason)
                VALUES (p_email, FALSE, 'Account locked - too many attempts');
                
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account locked due to multiple failed attempts';
            ELSE
                INSERT INTO login_attempts (email, successful, reason)
                VALUES (p_email, FALSE, 'Invalid password');
                
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid password';
            END IF;
        END IF;
    END IF;
END //

-- Enhanced User Login Procedure
CREATE PROCEDURE sp_user_login(
    IN p_email VARCHAR(100),
    IN p_password VARCHAR(255))
BEGIN
    DECLARE v_user_id INT;
    DECLARE v_password_hash VARCHAR(255);
    DECLARE v_status VARCHAR(20);
    DECLARE v_attempts INT;
    DECLARE v_last_attempt DATETIME;
    DECLARE v_role VARCHAR(20);
    
    SELECT id, password, status, login_attempts, last_attempt, role
    INTO v_user_id, v_password_hash, v_status, v_attempts, v_last_attempt, v_role
    FROM users 
    WHERE email = p_email;
    
    IF v_user_id IS NULL THEN
        -- Log failed attempt
        INSERT INTO login_attempts (email, successful, reason)
        VALUES (p_email, FALSE, 'User account not found');
        
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User account not found';
    ELSEIF v_status = 'locked' THEN
        -- Check if lock should be expired (30 minutes)
        IF TIMESTAMPDIFF(MINUTE, v_last_attempt, NOW()) > 30 THEN
            UPDATE users SET status = 'active', login_attempts = 0 WHERE id = v_user_id;
        ELSE
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account locked. Try again later or contact support';
        END IF;
    ELSEIF v_status = 'inactive' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account inactive. Contact support';
    ELSE
        IF password_verify(p_password, v_password_hash) THEN
            -- Successful login
            UPDATE users 
            SET login_attempts = 0,
                last_attempt = NULL,
                last_login = NOW()
            WHERE id = v_user_id;
            
            -- Log successful attempt
            INSERT INTO login_attempts (email, successful)
            VALUES (p_email, TRUE);
            
            -- Record in login history
            INSERT INTO login_history (user_id, ip_address, user_agent, was_successful)
            VALUES (v_user_id, SUBSTRING_INDEX(USER(), '@', -1), 
                   SUBSTRING_INDEX(USER(), '@', 1), TRUE);
            
            SELECT v_user_id AS user_id, v_role AS role, 'Login successful' AS message;
        ELSE
            -- Failed attempt
            SET v_attempts = v_attempts + 1;
            
            UPDATE users 
            SET login_attempts = v_attempts,
                last_attempt = NOW()
            WHERE id = v_user_id;
            
            -- Record failed attempt
            INSERT INTO login_history (user_id, ip_address, user_agent, was_successful, failure_reason)
            VALUES (v_user_id, SUBSTRING_INDEX(USER(), '@', -1), 
                   SUBSTRING_INDEX(USER(), '@', 1), FALSE, 'Invalid password');
            
            -- Lock account if too many attempts
            IF v_attempts >= 5 THEN
                UPDATE users SET status = 'locked' WHERE id = v_user_id;
                
                INSERT INTO login_attempts (email, successful, reason)
                VALUES (p_email, FALSE, 'Account locked - too many attempts');
                
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Account locked due to multiple failed attempts';
            ELSE
                INSERT INTO login_attempts (email, successful, reason)
                VALUES (p_email, FALSE, 'Invalid password');
                
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid password';
            END IF;
        END IF;
    END IF;
END //

-- Create Class Procedure
CREATE PROCEDURE sp_create_class(
    IN p_name VARCHAR(100),
    IN p_description TEXT,
    IN p_schedule DATETIME,
    IN p_trainer_id INT,
    IN p_capacity INT,
    IN p_price DECIMAL(10,2),
    IN p_class_type VARCHAR(20),
    IN p_admin_id INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION 
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error creating class';
    END;
    
    START TRANSACTION;
    
    -- Verify admin privileges
    IF NOT EXISTS (SELECT 1 FROM admin WHERE id = p_admin_id AND status = 'active') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Admin privileges required';
    END IF;
    
    -- Verify trainer exists
    IF NOT EXISTS (SELECT 1 FROM trainers WHERE id = p_trainer_id AND is_active = TRUE) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid or inactive trainer';
    END IF;
    
    -- Insert new class
    INSERT INTO classes (
        name, description, schedule, trainer_id, capacity, price, class_type, created_by
    ) VALUES (
        p_name, p_description, p_schedule, p_trainer_id, p_capacity, p_price, p_class_type, p_admin_id
    );
    
    COMMIT;
    
    SELECT LAST_INSERT_ID() AS class_id, 'Class created successfully' AS message;
END //

-- Update Class Procedure
CREATE PROCEDURE sp_update_class(
    IN p_class_id INT,
    IN p_name VARCHAR(100),
    IN p_description TEXT,
    IN p_schedule DATETIME,
    IN p_trainer_id INT,
    IN p_capacity INT,
    IN p_price DECIMAL(10,2),
    IN p_class_type VARCHAR(20),
    IN p_admin_id INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Error updating class';
    END;
    
    START TRANSACTION;
    
    -- Verify admin privileges
    IF NOT EXISTS (SELECT 1 FROM admin WHERE id = p_admin_id AND status = 'active') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Admin privileges required';
    END IF;
    
    -- Verify class exists
    IF NOT EXISTS (SELECT 1 FROM classes WHERE id = p_class_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Class not found';
    END IF;
    
    -- Verify trainer exists if changing trainer
    IF p_trainer_id IS NOT NULL AND 
       NOT EXISTS (SELECT 1 FROM trainers WHERE id = p_trainer_id AND is_active = TRUE) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid or inactive trainer';
    END IF;
    
    -- Update class
    UPDATE classes SET
        name = p_name,
        description = p_description,
        schedule = p_schedule,
        trainer_id = p_trainer_id,
        capacity = p_capacity,
        price = p_price,
        class_type = p_class_type,
        updated_at = NOW()
    WHERE id = p_class_id;
    
    COMMIT;
    
    SELECT p_class_id AS class_id, 'Class updated successfully' AS message;
END //

-- Password Reset Procedure
CREATE PROCEDURE sp_request_password_reset(
    IN p_email VARCHAR(100))
BEGIN
    DECLARE v_user_id INT;
    DECLARE v_token VARCHAR(255);
    DECLARE v_expires_at DATETIME;
    
    -- Find user
    SELECT id INTO v_user_id FROM users WHERE email = p_email;
    IF v_user_id IS NULL THEN
        SELECT NULL AS token, 'If the email exists, a reset link has been sent' AS message;
    ELSE
        -- Generate token
        SET v_token = SHA2(CONCAT(NOW(), RAND(), UUID()), 256);
        SET v_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR);
        
        -- Store token
        INSERT INTO password_resets (email, token, expires_at)
        VALUES (p_email, v_token, v_expires_at)
        ON DUPLICATE KEY UPDATE token = v_token, expires_at = v_expires_at;
        
        SELECT v_token AS token, 'Password reset token generated' AS message;
    END IF;
END //

DELIMITER ;

-- =============================================
-- DATABASE USERS AND PERMISSIONS
-- =============================================

-- Application User
DROP USER IF EXISTS 'fitzone_app'@'localhost';
CREATE USER 'fitzone_app'@'localhost' IDENTIFIED BY 'SecureAppPassword123!';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON fitzone.* TO 'fitzone_app'@'localhost';

-- Admin User
DROP USER IF EXISTS 'fitzone_admin'@'localhost';
CREATE USER 'fitzone_admin'@'localhost' IDENTIFIED BY 'SuperSecureAdminPassword456!';
GRANT ALL PRIVILEGES ON fitzone.* TO 'fitzone_admin'@'localhost';

FLUSH PRIVILEGES;

-- =============================================
-- VERIFICATION QUERIES
-- =============================================

-- Verify admin account
SELECT * FROM admin WHERE email = 'admin@fitzone.lk';

-- Verify password works (should return 1)
SELECT COUNT(*) AS password_match FROM admin 
WHERE email = 'admin@fitzone.lk' 
AND password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

-- List all tables
SHOW TABLES;