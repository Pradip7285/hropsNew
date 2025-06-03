-- Development Plans Management Tables

-- Main development plans table
CREATE TABLE IF NOT EXISTS development_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    plan_title VARCHAR(255) NOT NULL,
    description TEXT,
    development_focus VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    target_completion_date DATE NOT NULL,
    status ENUM('draft', 'active', 'on_hold', 'completed', 'cancelled') DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Development goals within plans
CREATE TABLE IF NOT EXISTS development_goals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_id INT NOT NULL,
    goal_title VARCHAR(255) NOT NULL,
    goal_description TEXT,
    target_date DATE NOT NULL,
    progress_percentage INT DEFAULT 0 CHECK (progress_percentage >= 0 AND progress_percentage <= 100),
    status ENUM('not_started', 'in_progress', 'completed', 'cancelled') DEFAULT 'not_started',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES development_plans(id) ON DELETE CASCADE
);

-- Learning resources assigned to development plans
CREATE TABLE IF NOT EXISTS development_resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_id INT NOT NULL,
    resource_type ENUM('course', 'book', 'article', 'video', 'workshop', 'certification', 'mentoring', 'other') NOT NULL,
    resource_title VARCHAR(255) NOT NULL,
    resource_url VARCHAR(500),
    description TEXT,
    status ENUM('assigned', 'in_progress', 'completed') DEFAULT 'assigned',
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (plan_id) REFERENCES development_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Development plan templates for reuse
CREATE TABLE IF NOT EXISTS development_plan_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(255) NOT NULL,
    description TEXT,
    development_focus VARCHAR(255) NOT NULL,
    duration_months INT DEFAULT 12,
    is_active BOOLEAN DEFAULT true,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Template goals for development plan templates
CREATE TABLE IF NOT EXISTS development_template_goals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_id INT NOT NULL,
    goal_title VARCHAR(255) NOT NULL,
    goal_description TEXT,
    target_month INT NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    goal_order INT NOT NULL,
    FOREIGN KEY (template_id) REFERENCES development_plan_templates(id) ON DELETE CASCADE
);

-- Performance Improvement Plans (PIP) Tables

-- Main PIP table
CREATE TABLE IF NOT EXISTS performance_improvement_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    supervisor_id INT NOT NULL,
    plan_title VARCHAR(255) NOT NULL,
    performance_issues TEXT NOT NULL,
    expected_outcomes TEXT NOT NULL,
    severity_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'completed_successful', 'completed_unsuccessful', 'terminated') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- PIP milestones and checkpoints
CREATE TABLE IF NOT EXISTS pip_milestones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pip_id INT NOT NULL,
    milestone_title VARCHAR(255) NOT NULL,
    milestone_description TEXT,
    target_date DATE NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'missed') DEFAULT 'pending',
    completion_date DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pip_id) REFERENCES performance_improvement_plans(id) ON DELETE CASCADE
);

-- Progress notes for PIPs
CREATE TABLE IF NOT EXISTS pip_progress_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pip_id INT NOT NULL,
    note_text TEXT NOT NULL,
    note_type ENUM('progress', 'concern', 'achievement', 'meeting', 'other') DEFAULT 'progress',
    added_by INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pip_id) REFERENCES performance_improvement_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE CASCADE
);

-- PIP meetings and reviews
CREATE TABLE IF NOT EXISTS pip_meetings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pip_id INT NOT NULL,
    meeting_date DATE NOT NULL,
    meeting_type ENUM('initial', 'progress_review', 'final_review', 'follow_up') NOT NULL,
    attendees TEXT,
    discussion_points TEXT,
    action_items TEXT,
    next_meeting_date DATE NULL,
    conducted_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pip_id) REFERENCES performance_improvement_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (conducted_by) REFERENCES users(id) ON DELETE CASCADE
);

-- PIP templates for consistency
CREATE TABLE IF NOT EXISTS pip_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(255) NOT NULL,
    severity_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    duration_days INT DEFAULT 90,
    performance_issues_template TEXT,
    expected_outcomes_template TEXT,
    milestone_templates JSON,
    is_active BOOLEAN DEFAULT true,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Performance analytics tracking
CREATE TABLE IF NOT EXISTS performance_analytics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    goals_count INT DEFAULT 0,
    goals_completed INT DEFAULT 0,
    reviews_count INT DEFAULT 0,
    average_rating DECIMAL(3,2),
    feedback_360_count INT DEFAULT 0,
    development_plans_count INT DEFAULT 0,
    pip_count INT DEFAULT 0,
    performance_score DECIMAL(5,2),
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_period (employee_id, period_start, period_end)
);

-- Manager-employee relationships for performance management
CREATE TABLE IF NOT EXISTS manager_employee_relationships (
    id INT PRIMARY KEY AUTO_INCREMENT,
    manager_id INT NOT NULL,
    employee_id INT NOT NULL,
    relationship_type ENUM('direct_report', 'dotted_line', 'matrix', 'temporary') DEFAULT 'direct_report',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_relationship (manager_id, employee_id, is_active)
);

-- Performance settings and configurations
CREATE TABLE IF NOT EXISTS performance_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_by INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default performance settings
INSERT INTO performance_settings (setting_key, setting_value, setting_type, description, updated_by) VALUES
('default_goal_duration_months', '12', 'integer', 'Default duration for performance goals in months', 1),
('review_cycle_frequency_months', '6', 'integer', 'How often performance reviews are conducted', 1),
('pip_default_duration_days', '90', 'integer', 'Default duration for Performance Improvement Plans', 1),
('development_plan_default_duration_months', '12', 'integer', 'Default duration for development plans', 1),
('enable_360_feedback', 'true', 'boolean', 'Enable 360-degree feedback functionality', 1),
('require_manager_approval_goals', 'true', 'boolean', 'Require manager approval for goal completion', 1),
('auto_calculate_performance_score', 'true', 'boolean', 'Automatically calculate performance scores', 1),
('performance_score_weights', '{"goals": 40, "reviews": 30, "feedback_360": 20, "development": 10}', 'json', 'Weights for calculating performance scores', 1)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Indexes for better performance
CREATE INDEX idx_development_plans_employee ON development_plans(employee_id);
CREATE INDEX idx_development_plans_status ON development_plans(status);
CREATE INDEX idx_development_plans_created_by ON development_plans(created_by);
CREATE INDEX idx_development_plans_dates ON development_plans(start_date, target_completion_date);

CREATE INDEX idx_development_goals_plan ON development_goals(plan_id);
CREATE INDEX idx_development_goals_status ON development_goals(status);
CREATE INDEX idx_development_goals_target_date ON development_goals(target_date);

CREATE INDEX idx_development_resources_plan ON development_resources(plan_id);
CREATE INDEX idx_development_resources_status ON development_resources(status);
CREATE INDEX idx_development_resources_assigned_by ON development_resources(assigned_by);

CREATE INDEX idx_pip_employee ON performance_improvement_plans(employee_id);
CREATE INDEX idx_pip_supervisor ON performance_improvement_plans(supervisor_id);
CREATE INDEX idx_pip_status ON performance_improvement_plans(status);
CREATE INDEX idx_pip_severity ON performance_improvement_plans(severity_level);
CREATE INDEX idx_pip_dates ON performance_improvement_plans(start_date, end_date);

CREATE INDEX idx_pip_milestones_pip ON pip_milestones(pip_id);
CREATE INDEX idx_pip_milestones_status ON pip_milestones(status);
CREATE INDEX idx_pip_milestones_target_date ON pip_milestones(target_date);

CREATE INDEX idx_pip_notes_pip ON pip_progress_notes(pip_id);
CREATE INDEX idx_pip_notes_added_by ON pip_progress_notes(added_by);
CREATE INDEX idx_pip_notes_type ON pip_progress_notes(note_type);

CREATE INDEX idx_pip_meetings_pip ON pip_meetings(pip_id);
CREATE INDEX idx_pip_meetings_date ON pip_meetings(meeting_date);
CREATE INDEX idx_pip_meetings_conducted_by ON pip_meetings(conducted_by);

CREATE INDEX idx_performance_analytics_employee ON performance_analytics(employee_id);
CREATE INDEX idx_performance_analytics_period ON performance_analytics(period_start, period_end);

CREATE INDEX idx_manager_relationships_manager ON manager_employee_relationships(manager_id);
CREATE INDEX idx_manager_relationships_employee ON manager_employee_relationships(employee_id);
CREATE INDEX idx_manager_relationships_active ON manager_employee_relationships(is_active); 