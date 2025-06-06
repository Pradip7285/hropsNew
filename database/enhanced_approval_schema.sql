-- Enhanced Approval Workflow Schema
-- Multi-level approvals, panel interviews, delegation, SLA management

-- Approval workflow configuration
CREATE TABLE IF NOT EXISTS approval_workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_name VARCHAR(100) NOT NULL,
    entity_type ENUM('offer', 'interview', 'role_transition', 'budget') NOT NULL,
    department VARCHAR(100),
    position_level ENUM('entry', 'mid', 'senior', 'lead', 'manager', 'director', 'vp', 'c_level') DEFAULT 'entry',
    salary_min DECIMAL(12,2) DEFAULT 0,
    salary_max DECIMAL(12,2) DEFAULT 999999999.99,
    approval_steps JSON,
    sla_hours INT DEFAULT 72,
    escalation_hours INT DEFAULT 168,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Individual approval instances
CREATE TABLE IF NOT EXISTS approval_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT NOT NULL,
    entity_type ENUM('offer', 'interview', 'role_transition', 'budget') NOT NULL,
    entity_id INT NOT NULL,
    current_step INT DEFAULT 1,
    total_steps INT NOT NULL,
    overall_status ENUM('pending', 'approved', 'rejected', 'escalated', 'cancelled') DEFAULT 'pending',
    initiated_by INT NOT NULL,
    initiated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    escalated_at TIMESTAMP NULL,
    metadata JSON,
    FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id),
    FOREIGN KEY (initiated_by) REFERENCES users(id)
);

-- Individual approval steps
CREATE TABLE IF NOT EXISTS approval_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instance_id INT NOT NULL,
    step_number INT NOT NULL,
    step_name VARCHAR(100) NOT NULL,
    required_role VARCHAR(50),
    required_user_id INT NULL,
    assigned_to INT NULL,
    delegated_to INT NULL,
    backup_approver_id INT NULL,
    status ENUM('pending', 'approved', 'rejected', 'delegated', 'escalated', 'skipped') DEFAULT 'pending',
    decision_date TIMESTAMP NULL,
    comments TEXT,
    due_date TIMESTAMP NULL,
    escalation_date TIMESTAMP NULL,
    is_committee_vote BOOLEAN DEFAULT FALSE,
    minimum_votes INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instance_id) REFERENCES approval_instances(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (delegated_to) REFERENCES users(id),
    FOREIGN KEY (backup_approver_id) REFERENCES users(id)
);

-- Committee voting for senior positions
CREATE TABLE IF NOT EXISTS committee_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    approval_step_id INT NOT NULL,
    committee_member_id INT NOT NULL,
    vote ENUM('approve', 'reject', 'abstain') NULL,
    comments TEXT,
    voted_at TIMESTAMP NULL,
    weight DECIMAL(3,2) DEFAULT 1.0,
    FOREIGN KEY (approval_step_id) REFERENCES approval_steps(id),
    FOREIGN KEY (committee_member_id) REFERENCES users(id),
    UNIQUE KEY unique_vote (approval_step_id, committee_member_id)
);

-- Interview panels
CREATE TABLE IF NOT EXISTS interview_panels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    interview_id INT NOT NULL,
    panel_name VARCHAR(200),
    panel_type ENUM('technical', 'behavioral', 'cultural', 'final', 'executive') DEFAULT 'technical',
    lead_interviewer_id INT NOT NULL,
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    scheduled_date DATETIME NOT NULL,
    duration INT DEFAULT 60,
    location VARCHAR(255),
    meeting_link VARCHAR(255),
    evaluation_criteria JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (interview_id) REFERENCES interviews(id),
    FOREIGN KEY (lead_interviewer_id) REFERENCES users(id)
);

-- Panel members
CREATE TABLE IF NOT EXISTS interview_panel_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    panel_id INT NOT NULL,
    interviewer_id INT NOT NULL,
    role ENUM('lead', 'technical', 'behavioral', 'observer', 'note_taker') DEFAULT 'technical',
    attendance_status ENUM('invited', 'confirmed', 'attended', 'absent') DEFAULT 'invited',
    feedback_submitted BOOLEAN DEFAULT FALSE,
    weight DECIMAL(3,2) DEFAULT 1.0,
    FOREIGN KEY (panel_id) REFERENCES interview_panels(id),
    FOREIGN KEY (interviewer_id) REFERENCES users(id),
    UNIQUE KEY unique_panel_member (panel_id, interviewer_id)
);

-- Enhanced interview feedback for panels
CREATE TABLE IF NOT EXISTS panel_interview_feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    panel_id INT NOT NULL,
    interviewer_id INT NOT NULL,
    technical_rating INT CHECK (technical_rating BETWEEN 1 AND 5),
    communication_rating INT CHECK (communication_rating BETWEEN 1 AND 5),
    cultural_fit_rating INT CHECK (cultural_fit_rating BETWEEN 1 AND 5),
    leadership_rating INT CHECK (leadership_rating BETWEEN 1 AND 5),
    problem_solving_rating INT CHECK (problem_solving_rating BETWEEN 1 AND 5),
    overall_rating INT CHECK (overall_rating BETWEEN 1 AND 5),
    strengths TEXT,
    weaknesses TEXT,
    concerns TEXT,
    recommendation ENUM('strong_hire', 'hire', 'neutral', 'no_hire', 'strong_no_hire'),
    feedback_notes TEXT,
    interview_duration INT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (panel_id) REFERENCES interview_panels(id),
    FOREIGN KEY (interviewer_id) REFERENCES users(id),
    UNIQUE KEY unique_panel_feedback (panel_id, interviewer_id)
);

-- Approval delegation
CREATE TABLE IF NOT EXISTS approval_delegations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delegator_id INT NOT NULL,
    delegate_id INT NOT NULL,
    delegation_scope ENUM('all', 'department', 'position_level', 'salary_range', 'specific') DEFAULT 'department',
    department VARCHAR(100),
    position_level VARCHAR(50),
    salary_min DECIMAL(12,2),
    salary_max DECIMAL(12,2),
    start_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delegator_id) REFERENCES users(id),
    FOREIGN KEY (delegate_id) REFERENCES users(id)
);

-- SLA tracking and escalations
CREATE TABLE IF NOT EXISTS approval_sla_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    approval_step_id INT NOT NULL,
    sla_target_hours INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    first_reminder_sent_at TIMESTAMP NULL,
    second_reminder_sent_at TIMESTAMP NULL,
    escalation_triggered_at TIMESTAMP NULL,
    escalated_to INT NULL,
    completed_at TIMESTAMP NULL,
    sla_met BOOLEAN NULL,
    hours_taken DECIMAL(8,2) NULL,
    FOREIGN KEY (approval_step_id) REFERENCES approval_steps(id),
    FOREIGN KEY (escalated_to) REFERENCES users(id)
);

-- Enhanced offers table updates
ALTER TABLE offers 
ADD COLUMN IF NOT EXISTS position_level ENUM('entry', 'mid', 'senior', 'lead', 'manager', 'director', 'vp', 'c_level') DEFAULT 'entry',
ADD COLUMN IF NOT EXISTS approval_instance_id INT NULL,
ADD COLUMN IF NOT EXISTS requires_committee_approval BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS budget_approval_required BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS offer_complexity ENUM('standard', 'complex', 'executive') DEFAULT 'standard';

-- Enhanced interviews table updates  
ALTER TABLE interviews
ADD COLUMN IF NOT EXISTS panel_id INT NULL,
ADD COLUMN IF NOT EXISTS approval_required BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS approval_instance_id INT NULL,
ADD COLUMN IF NOT EXISTS interview_complexity ENUM('standard', 'panel', 'executive') DEFAULT 'standard';

-- Enhanced role transitions table updates
CREATE TABLE IF NOT EXISTS role_transitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    transition_type ENUM('promotion', 'transfer', 'demotion', 'lateral_move') NOT NULL,
    current_position_id INT,
    proposed_position_id INT,
    current_department_id INT,
    proposed_department_id INT,
    effective_date DATE,
    transition_status ENUM('draft', 'submitted', 'under_review', 'approved', 'rejected', 'implemented') DEFAULT 'draft',
    reason_for_transition TEXT,
    approval_instance_id INT NULL,
    requires_budget_approval BOOLEAN DEFAULT FALSE,
    impact_assessment JSON,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Approval workflow templates
INSERT INTO approval_workflows (workflow_name, entity_type, position_level, salary_min, salary_max, approval_steps, sla_hours, escalation_hours) VALUES
('Standard Offer Approval', 'offer', 'entry', 0, 75000, JSON_ARRAY(
    JSON_OBJECT('step', 1, 'name', 'Hiring Manager Review', 'role', 'hiring_manager', 'required', true),
    JSON_OBJECT('step', 2, 'name', 'HR Final Review', 'role', 'hr_recruiter', 'required', true)
), 24, 72),

('Senior Offer Approval', 'offer', 'senior', 75000, 150000, JSON_ARRAY(
    JSON_OBJECT('step', 1, 'name', 'Hiring Manager Review', 'role', 'hiring_manager', 'required', true),
    JSON_OBJECT('step', 2, 'name', 'Department Head Review', 'role', 'department_head', 'required', true),
    JSON_OBJECT('step', 3, 'name', 'HR Director Review', 'role', 'hr_director', 'required', true)
), 48, 120),

('Executive Offer Approval', 'offer', 'director', 150000, 999999999.99, JSON_ARRAY(
    JSON_OBJECT('step', 1, 'name', 'Hiring Manager Review', 'role', 'hiring_manager', 'required', true),
    JSON_OBJECT('step', 2, 'name', 'Department Head Review', 'role', 'department_head', 'required', true),
    JSON_OBJECT('step', 3, 'name', 'HR Director Review', 'role', 'hr_director', 'required', true),
    JSON_OBJECT('step', 4, 'name', 'Executive Committee', 'role', 'executive', 'required', true, 'committee', true)
), 72, 168),

('Panel Interview Approval', 'interview', 'senior', 0, 999999999.99, JSON_ARRAY(
    JSON_OBJECT('step', 1, 'name', 'Hiring Manager Approval', 'role', 'hiring_manager', 'required', true),
    JSON_OBJECT('step', 2, 'name', 'Department Budget Review', 'role', 'department_head', 'required', true)
), 12, 24);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_approval_instances_entity ON approval_instances(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_approval_instances_status ON approval_instances(overall_status);
CREATE INDEX IF NOT EXISTS idx_approval_steps_status ON approval_steps(status);
CREATE INDEX IF NOT EXISTS idx_approval_steps_assigned ON approval_steps(assigned_to);
CREATE INDEX IF NOT EXISTS idx_approval_steps_due_date ON approval_steps(due_date);
CREATE INDEX IF NOT EXISTS idx_panel_members_interviewer ON interview_panel_members(interviewer_id);
CREATE INDEX IF NOT EXISTS idx_sla_tracking_step ON approval_sla_tracking(approval_step_id);
