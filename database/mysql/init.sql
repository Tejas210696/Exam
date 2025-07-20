-- Create database if not exists
CREATE DATABASE IF NOT EXISTS jee_portal;

-- Use the database
USE jee_portal;

-- Create ENUM types (MySQL uses ENUM directly)
-- User roles and statuses will be handled as ENUM in table definitions

-- Users table
CREATE TABLE users (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    role ENUM('STUDENT', 'ADMIN', 'PROCTOR', 'SUPER_ADMIN') NOT NULL DEFAULT 'STUDENT',
    status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED', 'PENDING') NOT NULL DEFAULT 'PENDING',
    phone VARCHAR(20),
    date_of_birth DATE,
    profile_image TEXT,
    email_verified_at TIMESTAMP NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- User profiles table
CREATE TABLE user_profiles (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id VARCHAR(36) NOT NULL,
    address_street TEXT,
    address_city VARCHAR(100),
    address_state VARCHAR(100),
    address_country VARCHAR(100),
    address_zip_code VARCHAR(20),
    language VARCHAR(10) DEFAULT 'en',
    timezone VARCHAR(50) DEFAULT 'UTC',
    email_notifications BOOLEAN DEFAULT true,
    sms_notifications BOOLEAN DEFAULT false,
    push_notifications BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Exams table
CREATE TABLE exams (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type ENUM('JEE_MAIN', 'JEE_ADVANCED', 'NEET', 'PRACTICE', 'MOCK', 'CUSTOM') NOT NULL,
    status ENUM('DRAFT', 'SCHEDULED', 'ACTIVE', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'DRAFT',
    duration INTEGER NOT NULL, -- in minutes
    total_marks INTEGER NOT NULL,
    passing_marks INTEGER NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    instructions JSON,
    settings JSON NOT NULL,
    created_by VARCHAR(36) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Exam submissions table
CREATE TABLE exam_submissions (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    exam_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NULL,
    status ENUM('IN_PROGRESS', 'SUBMITTED', 'AUTO_SUBMITTED', 'DISQUALIFIED') NOT NULL DEFAULT 'IN_PROGRESS',
    time_spent INTEGER DEFAULT 0, -- in seconds
    score DECIMAL(10,2),
    percentage DECIMAL(5,2),
    rank INTEGER,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_exam_user (exam_id, user_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Exam results table
CREATE TABLE exam_results (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    exam_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    submission_id VARCHAR(36) NOT NULL,
    total_questions INTEGER NOT NULL,
    attempted_questions INTEGER NOT NULL,
    correct_answers INTEGER NOT NULL,
    wrong_answers INTEGER NOT NULL,
    unanswered_questions INTEGER NOT NULL,
    score DECIMAL(10,2) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    rank INTEGER,
    total_candidates INTEGER,
    subject_wise_results JSON,
    time_analysis JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_exam_user_result (exam_id, user_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (submission_id) REFERENCES exam_submissions(id) ON DELETE CASCADE
);

-- Refresh tokens table (for JWT)
CREATE TABLE refresh_tokens (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id VARCHAR(36) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

-- Personal access tokens table (Laravel Sanctum)
CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tokenable (tokenable_type, tokenable_id)
);

-- Password reset tokens table
CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Failed jobs table (Laravel queue)
CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) UNIQUE NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Jobs table (Laravel queue)
CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL,
    reserved_at INTEGER UNSIGNED NULL,
    available_at INTEGER UNSIGNED NOT NULL,
    created_at INTEGER UNSIGNED NOT NULL,
    INDEX idx_queue_reserved_at (queue, reserved_at)
);

-- Job batches table (Laravel batch jobs)
CREATE TABLE job_batches (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INTEGER NOT NULL,
    pending_jobs INTEGER NOT NULL,
    failed_jobs INTEGER NOT NULL,
    failed_job_ids LONGTEXT NOT NULL,
    options MEDIUMTEXT,
    cancelled_at INTEGER NULL,
    created_at INTEGER NOT NULL,
    finished_at INTEGER NULL
);

-- Audit logs table
CREATE TABLE audit_logs (
    id VARCHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id VARCHAR(36),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id VARCHAR(36),
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_entity_type (entity_type)
);

-- Permissions and roles tables (Spatie Laravel Permission)
CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY permissions_name_guard_name_unique (name, guard_name)
);

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY roles_name_guard_name_unique (name, guard_name)
);

CREATE TABLE model_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    INDEX idx_model_has_permissions_model_id_model_type (model_id, model_type),
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (permission_id, model_id, model_type)
);

CREATE TABLE model_has_roles (
    role_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    INDEX idx_model_has_roles_model_id_model_type (model_id, model_type),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, model_id, model_type)
);

CREATE TABLE role_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (permission_id, role_id)
);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_created_at ON users(created_at);

CREATE INDEX idx_user_profiles_user_id ON user_profiles(user_id);

CREATE INDEX idx_exams_status ON exams(status);
CREATE INDEX idx_exams_type ON exams(type);
CREATE INDEX idx_exams_start_time ON exams(start_time);
CREATE INDEX idx_exams_created_by ON exams(created_by);

CREATE INDEX idx_exam_submissions_exam_id ON exam_submissions(exam_id);
CREATE INDEX idx_exam_submissions_user_id ON exam_submissions(user_id);
CREATE INDEX idx_exam_submissions_status ON exam_submissions(status);
CREATE INDEX idx_exam_submissions_created_at ON exam_submissions(created_at);

CREATE INDEX idx_exam_results_exam_id ON exam_results(exam_id);
CREATE INDEX idx_exam_results_user_id ON exam_results(user_id);
CREATE INDEX idx_exam_results_rank ON exam_results(rank);

-- Insert default admin user
INSERT INTO users (id, email, password, first_name, last_name, role, status, email_verified_at) 
VALUES (
    UUID(),
    'admin@jeeportal.com',
    '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj0kEjWNUKhm', -- password: admin123
    'System',
    'Administrator',
    'SUPER_ADMIN',
    'ACTIVE',
    NOW()
);

-- Insert sample student users
INSERT INTO users (id, email, password, first_name, last_name, role, status, email_verified_at) 
VALUES 
    (UUID(), 'student1@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj0kEjWNUKhm', 'John', 'Doe', 'STUDENT', 'ACTIVE', NOW()),
    (UUID(), 'student2@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj0kEjWNUKhm', 'Jane', 'Smith', 'STUDENT', 'ACTIVE', NOW()),
    (UUID(), 'proctor1@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj0kEjWNUKhm', 'Mike', 'Johnson', 'PROCTOR', 'ACTIVE', NOW());

-- Insert default permissions
INSERT INTO permissions (name, guard_name) VALUES
    ('view-dashboard', 'web'),
    ('manage-users', 'web'),
    ('manage-exams', 'web'),
    ('manage-questions', 'web'),
    ('view-results', 'web'),
    ('manage-settings', 'web'),
    ('take-exam', 'web'),
    ('view-own-results', 'web');

-- Insert default roles
INSERT INTO roles (name, guard_name) VALUES
    ('super-admin', 'web'),
    ('admin', 'web'),
    ('proctor', 'web'),
    ('student', 'web');

-- Assign permissions to roles
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'super-admin'; -- Super admin gets all permissions

INSERT INTO role_has_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'admin' AND p.name IN ('view-dashboard', 'manage-users', 'manage-exams', 'manage-questions', 'view-results');

INSERT INTO role_has_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'proctor' AND p.name IN ('view-dashboard', 'view-results');

INSERT INTO role_has_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.name = 'student' AND p.name IN ('take-exam', 'view-own-results');