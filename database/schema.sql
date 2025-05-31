-- HR Operations Database Schema
CREATE DATABASE IF NOT EXISTS hrops_db;
USE hrops_db;

-- Users table for authentication
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'hr_recruiter', 'hiring_manager', 'interviewer', 'employee') NOT NULL,
    department VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Job postings table
CREATE TABLE job_postings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT NOT NULL,
    department VARCHAR(100) NOT NULL,
    location VARCHAR(100) NOT NULL,
    salary_range VARCHAR(100),
    employment_type ENUM('full_time', 'part_time', 'contract', 'internship') DEFAULT 'full_time',
    status ENUM('active', 'closed', 'draft') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Candidates table
CREATE TABLE candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    linkedin_url VARCHAR(255),
    resume_path VARCHAR(255),
    skills TEXT,
    experience_years INT,
    current_location VARCHAR(100),
    status ENUM('new', 'shortlisted', 'interviewing', 'offered', 'hired', 'rejected') DEFAULT 'new',
    source VARCHAR(100),
    notes TEXT,
    ai_score DECIMAL(3,2),
    applied_for INT,
    assigned_to INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (applied_for) REFERENCES job_postings(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Interview schedules table
CREATE TABLE interviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    job_id INT NOT NULL,
    interviewer_id INT NOT NULL,
    interview_type ENUM('phone', 'video', 'in_person', 'technical') NOT NULL,
    scheduled_date DATETIME NOT NULL,
    duration INT DEFAULT 60,
    location VARCHAR(255),
    meeting_link VARCHAR(255),
    status ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id),
    FOREIGN KEY (job_id) REFERENCES job_postings(id),
    FOREIGN KEY (interviewer_id) REFERENCES users(id)
);

-- Interview feedback table
CREATE TABLE interview_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    interview_id INT NOT NULL,
    interviewer_id INT NOT NULL,
    technical_rating INT CHECK (technical_rating BETWEEN 1 AND 5),
    communication_rating INT CHECK (communication_rating BETWEEN 1 AND 5),
    cultural_fit_rating INT CHECK (cultural_fit_rating BETWEEN 1 AND 5),
    overall_rating INT CHECK (overall_rating BETWEEN 1 AND 5),
    strengths TEXT,
    weaknesses TEXT,
    recommendation ENUM('strong_hire', 'hire', 'neutral', 'no_hire', 'strong_no_hire'),
    feedback_notes TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (interview_id) REFERENCES interviews(id),
    FOREIGN KEY (interviewer_id) REFERENCES users(id)
);

-- Offers table
CREATE TABLE offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    job_id INT NOT NULL,
    salary_offered DECIMAL(10,2),
    benefits TEXT,
    start_date DATE,
    offer_letter_path VARCHAR(255),
    status ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') DEFAULT 'draft',
    valid_until DATE,
    created_by INT NOT NULL,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id),
    FOREIGN KEY (job_id) REFERENCES job_postings(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Employees table (for hired candidates)
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT NOT NULL,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    user_id INT,
    offer_id INT NOT NULL,
    start_date DATE NOT NULL,
    department VARCHAR(100) NOT NULL,
    position VARCHAR(100) NOT NULL,
    manager_id INT,
    buddy_id INT,
    onboarding_status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    documents_submitted BOOLEAN DEFAULT FALSE,
    it_setup_completed BOOLEAN DEFAULT FALSE,
    training_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (offer_id) REFERENCES offers(id),
    FOREIGN KEY (manager_id) REFERENCES users(id),
    FOREIGN KEY (buddy_id) REFERENCES users(id)
);

-- Onboarding tasks table
CREATE TABLE onboarding_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    task_name VARCHAR(200) NOT NULL,
    description TEXT,
    assigned_to INT,
    due_date DATE,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    completed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Documents table
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidate_id INT,
    employee_id INT,
    document_type VARCHAR(100) NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT,
    uploaded_by INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Activity logs table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default admin user
INSERT INTO users (username, email, password, first_name, last_name, role) 
VALUES ('admin', 'admin@hrops.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxkUJqpo7kpDyxBUxhELKCldXJq', 'System', 'Administrator', 'admin');
-- Default password is 'admin123' 