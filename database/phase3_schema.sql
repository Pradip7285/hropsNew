-- Additional tables for Phase 3: Offers, Onboarding, Analytics

-- Offers table
CREATE TABLE IF NOT EXISTS offers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NOT NULL,
    job_id INT NOT NULL,
    salary_offered DECIMAL(10,2) NOT NULL,
    benefits TEXT,
    start_date DATE,
    offer_letter_path VARCHAR(255),
    status ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') DEFAULT 'draft',
    valid_until DATE,
    created_by INT NOT NULL,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Offer templates
CREATE TABLE IF NOT EXISTS offer_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    variables JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Employees table (for hired candidates)
CREATE TABLE IF NOT EXISTS employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    candidate_id INT NULL,
    offer_id INT NULL,
    job_id INT NOT NULL,
    employee_id VARCHAR(50) UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    start_date DATE NOT NULL,
    salary DECIMAL(10,2),
    department VARCHAR(100),
    manager_id INT NULL,
    buddy_id INT NULL,
    onboarding_status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    completion_date DATE NULL,
    documents_complete BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id),
    FOREIGN KEY (offer_id) REFERENCES offers(id),
    FOREIGN KEY (job_id) REFERENCES job_postings(id),
    FOREIGN KEY (manager_id) REFERENCES users(id),
    FOREIGN KEY (buddy_id) REFERENCES users(id)
);

-- Onboarding tasks
CREATE TABLE IF NOT EXISTS onboarding_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    required BOOLEAN DEFAULT TRUE,
    due_date DATE,
    status ENUM('pending', 'in_progress', 'completed', 'skipped') DEFAULT 'pending',
    assigned_to INT NULL,
    completed_by INT NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (completed_by) REFERENCES users(id)
);

-- Onboarding task templates
CREATE TABLE IF NOT EXISTS onboarding_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    department VARCHAR(100),
    role_level ENUM('entry', 'mid', 'senior', 'executive') DEFAULT 'entry',
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Template tasks
CREATE TABLE IF NOT EXISTS template_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    required BOOLEAN DEFAULT TRUE,
    days_offset INT DEFAULT 0,
    assigned_role ENUM('hr', 'manager', 'it', 'admin', 'self') DEFAULT 'hr',
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES onboarding_templates(id) ON DELETE CASCADE
);

-- Employee documents
CREATE TABLE IF NOT EXISTS employee_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    document_name VARCHAR(200) NOT NULL,
    file_path VARCHAR(255),
    status ENUM('required', 'uploaded', 'verified', 'rejected') DEFAULT 'required',
    uploaded_at TIMESTAMP NULL,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    expiry_date DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- Document templates
CREATE TABLE IF NOT EXISTS document_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    required BOOLEAN DEFAULT TRUE,
    expires BOOLEAN DEFAULT FALSE,
    expiry_days INT NULL,
    department VARCHAR(100),
    role_level ENUM('entry', 'mid', 'senior', 'executive'),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Training modules
CREATE TABLE IF NOT EXISTS training_modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    content_type ENUM('video', 'document', 'quiz', 'external') DEFAULT 'document',
    content_url VARCHAR(255),
    duration_minutes INT,
    required BOOLEAN DEFAULT TRUE,
    department VARCHAR(100),
    order_index INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Employee training progress
CREATE TABLE IF NOT EXISTS training_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    module_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    progress_percentage INT DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    score INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES training_modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_module (employee_id, module_id)
);

-- Feedback and surveys
CREATE TABLE IF NOT EXISTS onboarding_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    feedback_type ENUM('week1', 'week2', 'month1', 'month3', 'exit') DEFAULT 'week1',
    overall_rating INT,
    onboarding_experience_rating INT,
    manager_support_rating INT,
    training_quality_rating INT,
    comments TEXT,
    suggestions TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- System settings
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    entity_type VARCHAR(50),
    entity_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default offer template
INSERT INTO offer_templates (name, content, variables, created_by) VALUES 
('Standard Offer Letter', 
'<h1>Job Offer Letter</h1>
<p>Dear {{candidate_name}},</p>
<p>We are pleased to offer you the position of <strong>{{job_title}}</strong> at {{company_name}}.</p>
<h3>Offer Details:</h3>
<ul>
    <li>Position: {{job_title}}</li>
    <li>Department: {{department}}</li>
    <li>Annual Salary: ${{salary}}</li>
    <li>Start Date: {{start_date}}</li>
</ul>
{{benefits_section}}
{{custom_terms}}
<p>This offer is contingent upon successful completion of background checks.</p>
<p>We look forward to having you join our team!</p>
<p>Sincerely,<br>HR Department</p>',
'{"variables": ["candidate_name", "job_title", "company_name", "department", "salary", "start_date", "benefits_section", "custom_terms"]}',
1);

-- Insert default onboarding template
INSERT INTO onboarding_templates (name, description, department, created_by) VALUES 
('General Employee Onboarding', 'Standard onboarding process for all new employees', 'All', 1);

-- Insert default template tasks
INSERT INTO template_tasks (template_id, title, description, category, required, days_offset, assigned_role, order_index) VALUES
(1, 'Complete I-9 Form', 'Verify employment eligibility', 'Documentation', 1, 0, 'hr', 1),
(1, 'Setup Email Account', 'Create company email and system access', 'IT Setup', 1, 0, 'it', 2),
(1, 'Office Tour', 'Familiarize with office layout and facilities', 'Orientation', 1, 1, 'manager', 3),
(1, 'Meet Team Members', 'Introduction to immediate team and key stakeholders', 'Social', 1, 1, 'manager', 4),
(1, 'Review Employee Handbook', 'Read and acknowledge company policies', 'Training', 1, 3, 'self', 5),
(1, 'Setup Direct Deposit', 'Configure payroll and benefits', 'HR', 1, 3, 'hr', 6),
(1, 'Safety Training', 'Complete workplace safety orientation', 'Training', 1, 5, 'hr', 7),
(1, 'Role-Specific Training', 'Department and position specific training', 'Training', 1, 7, 'manager', 8),
(1, 'First Week Check-in', 'Meet with manager to discuss progress', 'Review', 1, 7, 'manager', 9),
(1, 'Month 1 Review', 'Formal review of first month performance', 'Review', 1, 30, 'manager', 10);

-- Insert default document templates
INSERT INTO document_templates (name, description, required, expires, expiry_days, is_active) VALUES
('Driver License', 'Valid government-issued ID', 1, 0, NULL, 1),
('Social Security Card', 'Proof of SSN for I-9 verification', 1, 0, NULL, 1),
('Bank Information', 'Direct deposit setup form', 1, 0, NULL, 1),
('Emergency Contact Form', 'Emergency contact information', 1, 0, NULL, 1),
('Tax Forms (W-4)', 'Federal and state tax withholding', 1, 0, NULL, 1),
('Benefits Enrollment', 'Health insurance and benefits selection', 1, 0, NULL, 1),
('Background Check Authorization', 'Consent for background verification', 1, 0, NULL, 1),
('Employee Handbook Acknowledgment', 'Confirmation of policy review', 1, 0, NULL, 1);

-- Insert default training modules
INSERT INTO training_modules (title, description, content_type, duration_minutes, required, department, order_index, created_by) VALUES
('Company Overview', 'Introduction to company history, mission, and values', 'video', 30, 1, 'All', 1, 1),
('Workplace Safety', 'Essential safety protocols and procedures', 'video', 45, 1, 'All', 2, 1),
('Anti-Harassment Training', 'Workplace conduct and harassment prevention', 'video', 60, 1, 'All', 3, 1),
('IT Security Basics', 'Cybersecurity awareness and best practices', 'video', 20, 1, 'All', 4, 1),
('Communication Tools', 'Using company communication platforms', 'document', 15, 1, 'All', 5, 1),
('Time Management', 'Effective time management strategies', 'document', 25, 0, 'All', 6, 1);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('company_name', 'Your Company Name', 'string', 'Company name used in documents and communications'),
('onboarding_duration_days', '90', 'integer', 'Standard onboarding period in days'),
('require_document_verification', 'true', 'boolean', 'Whether documents require manual verification'),
('send_reminder_notifications', 'true', 'boolean', 'Send automatic reminder notifications'),
('default_buddy_assignment', 'true', 'boolean', 'Automatically assign buddy/mentor to new hires'),
('feedback_schedule', '["week1", "month1", "month3"]', 'json', 'When to send feedback surveys');

-- Update existing tables for Phase 3 compatibility
ALTER TABLE candidates ADD COLUMN offer_id INT NULL AFTER status;
ALTER TABLE candidates ADD FOREIGN KEY (offer_id) REFERENCES offers(id);

-- Add interview feedback relationship if not exists
ALTER TABLE interviews ADD COLUMN feedback_id INT NULL AFTER notes;
ALTER TABLE interviews ADD FOREIGN KEY (feedback_id) REFERENCES interview_feedback(id); 