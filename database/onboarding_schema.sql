-- Employee Onboarding Management System Database Schema

-- Create employees table (core employee records)
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) UNIQUE NOT NULL, -- Employee ID (e.g., EMP001)
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    job_id INT, -- Foreign key to job_postings
    department VARCHAR(100),
    position_title VARCHAR(150),
    start_date DATE,
    end_date DATE NULL,
    employment_type ENUM('full_time', 'part_time', 'contract', 'intern') DEFAULT 'full_time',
    salary DECIMAL(10,2),
    manager_id INT NULL, -- Foreign key to users table
    buddy_id INT NULL, -- Onboarding buddy/mentor
    office_location VARCHAR(100),
    work_arrangement ENUM('on_site', 'remote', 'hybrid') DEFAULT 'on_site',
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    onboarding_status ENUM('not_started', 'in_progress', 'completed', 'on_hold') DEFAULT 'not_started',
    onboarding_start_date TIMESTAMP NULL,
    onboarding_completion_date TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT, -- Foreign key to users
    FOREIGN KEY (job_id) REFERENCES job_postings(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (buddy_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create onboarding_templates table (reusable task templates)
CREATE TABLE IF NOT EXISTS onboarding_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    department VARCHAR(100),
    position_level ENUM('entry', 'mid', 'senior', 'executive') DEFAULT 'entry',
    duration_days INT DEFAULT 30, -- Expected completion time
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create onboarding_template_tasks table (tasks within templates)
CREATE TABLE IF NOT EXISTS onboarding_template_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL,
    task_name VARCHAR(200) NOT NULL,
    description TEXT,
    category ENUM('documentation', 'equipment', 'training', 'orientation', 'compliance', 'social', 'other') DEFAULT 'other',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    due_days INT DEFAULT 1, -- Due X days after start
    estimated_hours DECIMAL(3,1) DEFAULT 1.0,
    assignee_role ENUM('employee', 'hr', 'manager', 'buddy', 'it', 'other') DEFAULT 'employee',
    is_required BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    instructions TEXT,
    FOREIGN KEY (template_id) REFERENCES onboarding_templates(id) ON DELETE CASCADE
);

-- Create onboarding_tasks table (actual task instances for employees)
CREATE TABLE IF NOT EXISTS onboarding_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    template_task_id INT NULL, -- Reference to template task if created from template
    task_name VARCHAR(200) NOT NULL,
    description TEXT,
    category ENUM('documentation', 'equipment', 'training', 'orientation', 'compliance', 'social', 'other') DEFAULT 'other',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('pending', 'in_progress', 'completed', 'skipped', 'blocked') DEFAULT 'pending',
    due_date DATE,
    completed_date TIMESTAMP NULL,
    estimated_hours DECIMAL(3,1) DEFAULT 1.0,
    actual_hours DECIMAL(3,1) NULL,
    assigned_to INT, -- Foreign key to users (who should complete this)
    assignee_role ENUM('employee', 'hr', 'manager', 'buddy', 'it', 'other') DEFAULT 'employee',
    completed_by INT NULL, -- Foreign key to users (who actually completed it)
    is_required BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    instructions TEXT,
    completion_notes TEXT,
    attachments JSON, -- Store file paths for attachments
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (template_task_id) REFERENCES onboarding_template_tasks(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create onboarding_documents table (required documents tracking)
CREATE TABLE IF NOT EXISTS onboarding_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    document_name VARCHAR(200) NOT NULL,
    document_type ENUM('form', 'contract', 'policy', 'handbook', 'certificate', 'id', 'tax', 'benefits', 'other') DEFAULT 'other',
    is_required BOOLEAN DEFAULT TRUE,
    status ENUM('pending', 'submitted', 'approved', 'rejected', 'missing') DEFAULT 'pending',
    file_path VARCHAR(500),
    original_filename VARCHAR(255),
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_at TIMESTAMP NULL,
    reviewed_by INT NULL, -- Foreign key to users
    reviewed_at TIMESTAMP NULL,
    rejection_reason TEXT,
    notes TEXT,
    due_date DATE,
    template_document_id INT NULL, -- If created from template
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create training_modules table (learning content)
CREATE TABLE IF NOT EXISTS training_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    content TEXT, -- Training content/instructions
    module_type ENUM('reading', 'video', 'quiz', 'practical', 'meeting', 'other') DEFAULT 'reading',
    category VARCHAR(100), -- e.g., 'safety', 'compliance', 'product', 'company_culture'
    duration_minutes INT DEFAULT 30,
    is_required BOOLEAN DEFAULT TRUE,
    passing_score INT DEFAULT 80, -- For quizzes
    prerequisites JSON, -- IDs of prerequisite modules
    content_url VARCHAR(500), -- External content link
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create employee_training table (employee training progress)
CREATE TABLE IF NOT EXISTS employee_training (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    training_module_id INT NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed', 'failed', 'skipped') DEFAULT 'not_started',
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    score INT NULL, -- For quizzes/assessments
    attempts INT DEFAULT 0,
    time_spent_minutes INT DEFAULT 0,
    notes TEXT,
    assigned_date DATE,
    due_date DATE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (training_module_id) REFERENCES training_modules(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_training (employee_id, training_module_id)
);

-- Create onboarding_checklists table (overall progress tracking)
CREATE TABLE IF NOT EXISTS onboarding_checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    checklist_name VARCHAR(150) NOT NULL,
    total_tasks INT DEFAULT 0,
    completed_tasks INT DEFAULT 0,
    completion_percentage DECIMAL(5,2) DEFAULT 0.00,
    status ENUM('not_started', 'in_progress', 'completed', 'overdue') DEFAULT 'not_started',
    target_completion_date DATE,
    actual_completion_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Create onboarding_feedback table (feedback collection)
CREATE TABLE IF NOT EXISTS onboarding_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    feedback_type ENUM('weekly', 'milestone', 'completion', 'buddy', 'manager') DEFAULT 'weekly',
    week_number INT NULL, -- For weekly feedback
    rating INT, -- 1-5 scale
    feedback_text TEXT,
    suggestions TEXT,
    challenges TEXT,
    submitted_by INT, -- Who provided the feedback
    feedback_date DATE,
    is_anonymous BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create onboarding_meetings table (scheduled meetings/check-ins)
CREATE TABLE IF NOT EXISTS onboarding_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    meeting_type ENUM('welcome', 'one_on_one', 'team_intro', 'hr_checkin', 'buddy_meeting', 'milestone') DEFAULT 'one_on_one',
    title VARCHAR(200) NOT NULL,
    description TEXT,
    scheduled_date DATETIME,
    duration_minutes INT DEFAULT 60,
    location VARCHAR(200),
    meeting_url VARCHAR(500), -- For virtual meetings
    organizer_id INT, -- Foreign key to users
    attendees JSON, -- Array of user IDs
    status ENUM('scheduled', 'completed', 'cancelled', 'rescheduled') DEFAULT 'scheduled',
    completion_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for better performance
CREATE INDEX idx_employees_onboarding_status ON employees(onboarding_status);
CREATE INDEX idx_employees_start_date ON employees(start_date);
CREATE INDEX idx_employees_department ON employees(department);
CREATE INDEX idx_onboarding_tasks_employee_id ON onboarding_tasks(employee_id);
CREATE INDEX idx_onboarding_tasks_status ON onboarding_tasks(status);
CREATE INDEX idx_onboarding_tasks_due_date ON onboarding_tasks(due_date);
CREATE INDEX idx_employee_training_employee_id ON employee_training(employee_id);
CREATE INDEX idx_employee_training_status ON employee_training(status);
CREATE INDEX idx_onboarding_documents_employee_id ON onboarding_documents(employee_id);
CREATE INDEX idx_onboarding_documents_status ON onboarding_documents(status);
CREATE INDEX idx_onboarding_meetings_employee_id ON onboarding_meetings(employee_id);
CREATE INDEX idx_onboarding_meetings_date ON onboarding_meetings(scheduled_date);

-- Insert default onboarding templates
INSERT INTO onboarding_templates (name, description, department, position_level, duration_days, created_by) VALUES
('General Employee Onboarding', 'Standard onboarding process for all new employees', 'All', 'entry', 14, 1),
('Technical Staff Onboarding', 'Specialized onboarding for technical positions', 'Engineering', 'mid', 21, 1),
('Management Onboarding', 'Leadership-focused onboarding process', 'Management', 'senior', 30, 1),
('Sales Team Onboarding', 'Sales-specific training and setup', 'Sales', 'entry', 21, 1);

-- Insert default template tasks for General Employee Onboarding
INSERT INTO onboarding_template_tasks (template_id, task_name, description, category, priority, due_days, assignee_role, instructions) VALUES
(1, 'Complete I-9 Form', 'Verify eligibility to work in the United States', 'documentation', 'critical', 1, 'employee', 'Bring required identification documents to HR'),
(1, 'Set up Direct Deposit', 'Provide banking information for payroll', 'documentation', 'high', 2, 'employee', 'Complete direct deposit form with HR'),
(1, 'IT Equipment Setup', 'Receive and configure laptop, phone, accounts', 'equipment', 'high', 1, 'it', 'IT will provide laptop and set up accounts'),
(1, 'Office Tour & Badge Photo', 'Get familiar with office layout and security badge', 'orientation', 'medium', 1, 'hr', 'HR will conduct office tour and take badge photo'),
(1, 'Meet Your Team', 'Introduction meeting with immediate team members', 'social', 'medium', 2, 'manager', 'Manager will organize team introduction'),
(1, 'Complete Benefits Enrollment', 'Select health, dental, and other benefits', 'documentation', 'high', 5, 'employee', 'Review benefits package and make selections'),
(1, 'Read Employee Handbook', 'Review company policies and procedures', 'training', 'medium', 7, 'employee', 'Available in employee portal - complete acknowledgment'),
(1, 'Compliance Training', 'Complete required compliance and safety training', 'compliance', 'critical', 10, 'employee', 'Complete online training modules'),
(1, 'First Week Manager Check-in', 'One-on-one meeting with direct manager', 'orientation', 'high', 5, 'manager', 'Discuss role expectations and answer questions'),
(1, 'Two Week Progress Review', 'Review onboarding progress and address concerns', 'orientation', 'medium', 14, 'hr', 'HR will schedule review meeting');

-- Insert sample training modules
INSERT INTO training_modules (title, description, module_type, category, duration_minutes, is_required, created_by) VALUES
('Company Culture & Values', 'Learn about our company mission, values, and culture', 'reading', 'company_culture', 45, TRUE, 1),
('Workplace Safety Training', 'Essential safety procedures and emergency protocols', 'video', 'safety', 60, TRUE, 1),
('Anti-Harassment Policy', 'Understanding workplace harassment policies and reporting', 'reading', 'compliance', 30, TRUE, 1),
('Data Security & Privacy', 'Information security best practices and data protection', 'quiz', 'compliance', 90, TRUE, 1),
('Communication Tools Training', 'How to use Slack, email, and collaboration tools', 'practical', 'tools', 30, TRUE, 1),
('Time Tracking & PTO Policy', 'Understanding time tracking system and vacation policies', 'reading', 'policies', 20, TRUE, 1); 