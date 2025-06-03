-- Performance Management System Database Schema
-- Created: <?php echo date('Y-m-d H:i:s'); ?>

-- Performance Goals and Objectives
CREATE TABLE performance_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    manager_id INT NOT NULL,
    goal_title VARCHAR(255) NOT NULL,
    goal_description TEXT,
    goal_category ENUM('individual', 'team', 'organizational', 'development', 'behavioral') DEFAULT 'individual',
    goal_type ENUM('objective', 'key_result', 'milestone', 'competency') DEFAULT 'objective',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('draft', 'active', 'in_progress', 'completed', 'paused', 'cancelled') DEFAULT 'draft',
    target_value DECIMAL(10,2) NULL,
    current_value DECIMAL(10,2) DEFAULT 0.00,
    unit_of_measure VARCHAR(50) NULL,
    start_date DATE NOT NULL,
    due_date DATE NOT NULL,
    completion_date DATE NULL,
    weight_percentage DECIMAL(5,2) DEFAULT 0.00,
    progress_percentage DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Performance Review Cycles
CREATE TABLE performance_cycles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_name VARCHAR(255) NOT NULL,
    cycle_type ENUM('annual', 'semi_annual', 'quarterly', 'monthly', 'project_based') DEFAULT 'annual',
    cycle_year YEAR NOT NULL,
    cycle_period VARCHAR(50) NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    review_deadline DATE NOT NULL,
    status ENUM('draft', 'active', 'in_review', 'completed', 'archived') DEFAULT 'draft',
    description TEXT NULL,
    instructions TEXT NULL,
    is_360_enabled BOOLEAN DEFAULT FALSE,
    is_self_review_enabled BOOLEAN DEFAULT TRUE,
    is_manager_review_enabled BOOLEAN DEFAULT TRUE,
    is_peer_review_enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Performance Reviews
CREATE TABLE performance_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id INT NOT NULL,
    employee_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    review_type ENUM('self', 'manager', 'peer', '360', 'skip_level') NOT NULL,
    status ENUM('not_started', 'in_progress', 'submitted', 'reviewed', 'completed') DEFAULT 'not_started',
    overall_rating DECIMAL(3,2) NULL,
    overall_comments TEXT NULL,
    strengths TEXT NULL,
    areas_for_improvement TEXT NULL,
    achievements TEXT NULL,
    development_needs TEXT NULL,
    goals_for_next_period TEXT NULL,
    submitted_at TIMESTAMP NULL,
    reviewed_at TIMESTAMP NULL,
    due_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cycle_id) REFERENCES performance_cycles(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES employees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_review (cycle_id, employee_id, reviewer_id, review_type)
);

-- Performance Review Ratings (Detailed ratings by competency/goal)
CREATE TABLE performance_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    rating_category ENUM('goal', 'competency', 'behavior', 'skill', 'overall') NOT NULL,
    category_id INT NULL,
    rating_name VARCHAR(255) NOT NULL,
    rating_description TEXT NULL,
    rating_value DECIMAL(3,2) NOT NULL,
    max_rating DECIMAL(3,2) DEFAULT 5.00,
    weight_percentage DECIMAL(5,2) DEFAULT 0.00,
    comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES performance_reviews(id) ON DELETE CASCADE
);

-- 360-Degree Feedback
CREATE TABLE feedback_360 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cycle_id INT NOT NULL,
    employee_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    reviewer_relationship ENUM('manager', 'direct_report', 'peer', 'internal_customer', 'external_customer', 'other') NOT NULL,
    status ENUM('invited', 'in_progress', 'submitted', 'completed') DEFAULT 'invited',
    invitation_sent_at TIMESTAMP NULL,
    started_at TIMESTAMP NULL,
    submitted_at TIMESTAMP NULL,
    anonymous BOOLEAN DEFAULT TRUE,
    overall_rating DECIMAL(3,2) NULL,
    feedback_text TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cycle_id) REFERENCES performance_cycles(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- 360 Feedback Questions and Responses
CREATE TABLE feedback_360_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feedback_id INT NOT NULL,
    question_id INT NOT NULL,
    question_text TEXT NOT NULL,
    response_type ENUM('rating', 'text', 'multiple_choice') NOT NULL,
    rating_value DECIMAL(3,2) NULL,
    text_response TEXT NULL,
    choice_response VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feedback_id) REFERENCES feedback_360(id) ON DELETE CASCADE
);

-- Individual Development Plans (IDPs)
CREATE TABLE development_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    manager_id INT NOT NULL,
    plan_title VARCHAR(255) NOT NULL,
    plan_description TEXT,
    plan_year YEAR NOT NULL,
    status ENUM('draft', 'active', 'in_progress', 'completed', 'on_hold') DEFAULT 'draft',
    start_date DATE NOT NULL,
    target_completion_date DATE NOT NULL,
    actual_completion_date DATE NULL,
    career_goals TEXT NULL,
    skill_gaps TEXT NULL,
    development_priorities TEXT NULL,
    success_metrics TEXT NULL,
    budget_allocated DECIMAL(10,2) DEFAULT 0.00,
    budget_used DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Development Plan Activities
CREATE TABLE development_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    activity_title VARCHAR(255) NOT NULL,
    activity_description TEXT,
    activity_type ENUM('training', 'mentoring', 'coaching', 'project', 'stretch_assignment', 'conference', 'certification', 'reading', 'other') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('planned', 'in_progress', 'completed', 'cancelled', 'on_hold') DEFAULT 'planned',
    start_date DATE NOT NULL,
    due_date DATE NOT NULL,
    completion_date DATE NULL,
    cost DECIMAL(10,2) DEFAULT 0.00,
    provider VARCHAR(255) NULL,
    notes TEXT NULL,
    success_criteria TEXT NULL,
    outcome_achieved TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES development_plans(id) ON DELETE CASCADE
);

-- Performance Improvement Plans (PIPs)
CREATE TABLE improvement_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    manager_id INT NOT NULL,
    hr_representative_id INT NULL,
    plan_title VARCHAR(255) NOT NULL,
    plan_description TEXT,
    performance_issues TEXT NOT NULL,
    improvement_goals TEXT NOT NULL,
    success_criteria TEXT NOT NULL,
    consequences TEXT NULL,
    support_resources TEXT NULL,
    status ENUM('draft', 'active', 'in_progress', 'successful', 'unsuccessful', 'terminated') DEFAULT 'draft',
    start_date DATE NOT NULL,
    review_date DATE NOT NULL,
    end_date DATE NOT NULL,
    final_outcome ENUM('successful', 'unsuccessful', 'extended', 'terminated') NULL,
    final_outcome_date DATE NULL,
    final_outcome_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (hr_representative_id) REFERENCES employees(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- PIP Milestones and Check-ins
CREATE TABLE improvement_milestones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    milestone_title VARCHAR(255) NOT NULL,
    milestone_description TEXT,
    due_date DATE NOT NULL,
    status ENUM('pending', 'in_progress', 'met', 'not_met', 'partially_met') DEFAULT 'pending',
    completion_date DATE NULL,
    manager_comments TEXT NULL,
    employee_comments TEXT NULL,
    evidence_provided TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES improvement_plans(id) ON DELETE CASCADE
);

-- Performance Competencies (Skills/Behaviors being evaluated)
CREATE TABLE performance_competencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    competency_name VARCHAR(255) NOT NULL,
    competency_description TEXT,
    competency_category ENUM('technical', 'behavioral', 'leadership', 'communication', 'problem_solving', 'teamwork', 'other') DEFAULT 'behavioral',
    proficiency_levels JSON NULL, -- Store different proficiency levels
    is_active BOOLEAN DEFAULT TRUE,
    applies_to_roles JSON NULL, -- Store roles this competency applies to
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Performance Templates (Reusable review templates)
CREATE TABLE performance_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(255) NOT NULL,
    template_type ENUM('review', 'goal_setting', 'development_plan', 'improvement_plan') NOT NULL,
    template_description TEXT,
    template_content JSON NOT NULL, -- Store template structure
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    applicable_roles JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Performance Notes and Comments
CREATE TABLE performance_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    noted_by INT NOT NULL,
    note_type ENUM('achievement', 'concern', 'feedback', 'coaching', 'recognition', 'development', 'other') NOT NULL,
    note_title VARCHAR(255) NOT NULL,
    note_content TEXT NOT NULL,
    visibility ENUM('private', 'manager', 'hr', 'employee') DEFAULT 'manager',
    is_formal BOOLEAN DEFAULT FALSE,
    related_goal_id INT NULL,
    related_review_id INT NULL,
    tags JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (noted_by) REFERENCES users(id),
    FOREIGN KEY (related_goal_id) REFERENCES performance_goals(id) ON DELETE SET NULL,
    FOREIGN KEY (related_review_id) REFERENCES performance_reviews(id) ON DELETE SET NULL
);

-- Performance Analytics and Metrics
CREATE TABLE performance_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    cycle_id INT NULL,
    metric_type ENUM('goal_completion', 'review_score', 'improvement_rate', 'development_progress', 'competency_score') NOT NULL,
    metric_name VARCHAR(255) NOT NULL,
    metric_value DECIMAL(10,4) NOT NULL,
    metric_period VARCHAR(50) NOT NULL,
    calculation_date DATE NOT NULL,
    additional_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (cycle_id) REFERENCES performance_cycles(id) ON DELETE SET NULL
);

-- Create indexes for better performance
CREATE INDEX idx_performance_goals_employee ON performance_goals(employee_id);
CREATE INDEX idx_performance_goals_manager ON performance_goals(manager_id);
CREATE INDEX idx_performance_goals_status ON performance_goals(status);
CREATE INDEX idx_performance_goals_due_date ON performance_goals(due_date);

CREATE INDEX idx_performance_reviews_cycle ON performance_reviews(cycle_id);
CREATE INDEX idx_performance_reviews_employee ON performance_reviews(employee_id);
CREATE INDEX idx_performance_reviews_reviewer ON performance_reviews(reviewer_id);
CREATE INDEX idx_performance_reviews_status ON performance_reviews(status);

CREATE INDEX idx_feedback_360_employee ON feedback_360(employee_id);
CREATE INDEX idx_feedback_360_reviewer ON feedback_360(reviewer_id);
CREATE INDEX idx_feedback_360_status ON feedback_360(status);

CREATE INDEX idx_development_plans_employee ON development_plans(employee_id);
CREATE INDEX idx_development_plans_status ON development_plans(status);
CREATE INDEX idx_development_activities_plan ON development_activities(plan_id);

CREATE INDEX idx_improvement_plans_employee ON improvement_plans(employee_id);
CREATE INDEX idx_improvement_plans_status ON improvement_plans(status);
CREATE INDEX idx_improvement_milestones_plan ON improvement_milestones(plan_id);

CREATE INDEX idx_performance_notes_employee ON performance_notes(employee_id);
CREATE INDEX idx_performance_notes_type ON performance_notes(note_type);
CREATE INDEX idx_performance_analytics_employee ON performance_analytics(employee_id); 