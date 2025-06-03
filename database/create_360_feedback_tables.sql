-- 360° Feedback System Tables

-- Check if performance_cycles table exists, if not create it
CREATE TABLE IF NOT EXISTS performance_cycles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cycle_name VARCHAR(255) NOT NULL,
    cycle_year YEAR NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Main 360° feedback requests table
CREATE TABLE IF NOT EXISTS feedback_360_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    cycle_id INT NOT NULL,
    requested_by INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    deadline DATE NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (cycle_id) REFERENCES performance_cycles(id) ON DELETE CASCADE
);

-- Feedback providers (who will provide feedback)
CREATE TABLE IF NOT EXISTS feedback_360_providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    provider_id INT NOT NULL,
    relationship_type ENUM('self', 'manager', 'peer', 'subordinate', 'other') NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'declined') DEFAULT 'pending',
    invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (request_id) REFERENCES feedback_360_requests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_provider_request (request_id, provider_id)
);

-- Feedback questions for each request
CREATE TABLE IF NOT EXISTS feedback_360_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('text', 'rating_5', 'rating_10', 'multiple_choice') DEFAULT 'text',
    question_order INT NOT NULL,
    required BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES feedback_360_requests(id) ON DELETE CASCADE
);

-- Feedback responses/submissions
CREATE TABLE IF NOT EXISTS feedback_360_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    provider_id INT NOT NULL,
    responses JSON NOT NULL,
    overall_rating INT NULL CHECK (overall_rating >= 1 AND overall_rating <= 5),
    comments TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES feedback_360_requests(id) ON DELETE CASCADE,
    UNIQUE KEY unique_response (request_id, provider_id)
);

-- Feedback templates for reusable question sets
CREATE TABLE IF NOT EXISTS feedback_360_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Template questions
CREATE TABLE IF NOT EXISTS feedback_360_template_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('text', 'rating_5', 'rating_10', 'multiple_choice') DEFAULT 'text',
    question_order INT NOT NULL,
    required BOOLEAN DEFAULT false,
    FOREIGN KEY (template_id) REFERENCES feedback_360_templates(id) ON DELETE CASCADE
);

-- Reminders sent to providers
CREATE TABLE IF NOT EXISTS feedback_360_reminders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    request_id INT NOT NULL,
    sent_by INT NOT NULL,
    reminder_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES feedback_360_requests(id) ON DELETE CASCADE
);

-- Feedback analytics and insights
CREATE TABLE IF NOT EXISTS feedback_360_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    cycle_id INT NOT NULL,
    total_requests INT DEFAULT 0,
    completed_requests INT DEFAULT 0,
    average_rating DECIMAL(3,2),
    strengths TEXT,
    areas_for_improvement TEXT,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (cycle_id) REFERENCES performance_cycles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_cycle (employee_id, cycle_id)
);

-- Indexes for better performance
CREATE INDEX idx_feedback_requests_employee ON feedback_360_requests(employee_id);
CREATE INDEX idx_feedback_requests_cycle ON feedback_360_requests(cycle_id);
CREATE INDEX idx_feedback_requests_status ON feedback_360_requests(status);
CREATE INDEX idx_feedback_requests_deadline ON feedback_360_requests(deadline);

CREATE INDEX idx_providers_request ON feedback_360_providers(request_id);
CREATE INDEX idx_providers_provider ON feedback_360_providers(provider_id);
CREATE INDEX idx_providers_status ON feedback_360_providers(status);

CREATE INDEX idx_questions_request ON feedback_360_questions(request_id);
CREATE INDEX idx_questions_order ON feedback_360_questions(question_order);

CREATE INDEX idx_responses_request ON feedback_360_responses(request_id);
CREATE INDEX idx_responses_provider ON feedback_360_responses(provider_id);

CREATE INDEX idx_templates_active ON feedback_360_templates(is_active);

CREATE INDEX idx_template_questions_template ON feedback_360_template_questions(template_id);
CREATE INDEX idx_template_questions_order ON feedback_360_template_questions(question_order);

CREATE INDEX idx_reminders_provider ON feedback_360_reminders(provider_id);
CREATE INDEX idx_reminders_request ON feedback_360_reminders(request_id);

CREATE INDEX idx_analytics_employee ON feedback_360_analytics(employee_id);
CREATE INDEX idx_analytics_cycle ON feedback_360_analytics(cycle_id); 