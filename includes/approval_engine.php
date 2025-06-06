<?php
/**
 * Enhanced Approval Workflow Engine
 * Handles multi-level approvals, delegation, SLA management, and escalation
 */

class ApprovalEngine {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Initialize approval workflow for an entity
     */
    public function initiateApproval($entity_type, $entity_id, $context = []) {
        try {
            // Find appropriate workflow
            $workflow = $this->findWorkflow($entity_type, $context);
            
            if (!$workflow) {
                throw new Exception("No matching approval workflow found");
            }
            
            // Create approval instance
            $instance_id = $this->createApprovalInstance($workflow, $entity_type, $entity_id, $context);
            
            // Create approval steps
            $this->createApprovalSteps($instance_id, $workflow, $context);
            
            // Start first step
            $this->startNextStep($instance_id);
            
            return $instance_id;
            
        } catch (Exception $e) {
            error_log("Approval initiation error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Find matching workflow based on context
     */
    private function findWorkflow($entity_type, $context) {
        $sql = "
            SELECT * FROM approval_workflows 
            WHERE entity_type = ? 
            AND is_active = TRUE
            AND (department IS NULL OR department = ?)
            AND (position_level IS NULL OR position_level = ?)
            AND salary_min <= ? 
            AND salary_max >= ?
            ORDER BY 
                (department IS NOT NULL) DESC,
                (position_level IS NOT NULL) DESC,
                salary_max ASC
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $entity_type,
            $context['department'] ?? null,
            $context['position_level'] ?? 'entry',
            $context['salary'] ?? 0,
            $context['salary'] ?? 0
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create approval instance
     */
    private function createApprovalInstance($workflow, $entity_type, $entity_id, $context) {
        $steps = json_decode($workflow['approval_steps'], true);
        
        $sql = "
            INSERT INTO approval_instances 
            (workflow_id, entity_type, entity_id, total_steps, initiated_by, metadata)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $workflow['id'],
            $entity_type,
            $entity_id,
            count($steps),
            $_SESSION['user_id'],
            json_encode($context)
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    /**
     * Create approval steps
     */
    private function createApprovalSteps($instance_id, $workflow, $context) {
        $steps = json_decode($workflow['approval_steps'], true);
        
        foreach ($steps as $step) {
            $assigned_to = $this->findApprover($step, $context);
            $due_date = date('Y-m-d H:i:s', strtotime("+{$workflow['sla_hours']} hours"));
            $escalation_date = date('Y-m-d H:i:s', strtotime("+{$workflow['escalation_hours']} hours"));
            
            $sql = "
                INSERT INTO approval_steps 
                (instance_id, step_number, step_name, required_role, assigned_to, 
                 due_date, escalation_date, is_committee_vote, minimum_votes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $instance_id,
                $step['step'],
                $step['name'],
                $step['role'],
                $assigned_to,
                $due_date,
                $escalation_date,
                isset($step['committee']) && $step['committee'],
                $step['minimum_votes'] ?? 1
            ]);
            
            $step_id = $this->conn->lastInsertId();
            
            // Create SLA tracking
            $this->createSLATracking($step_id, $workflow['sla_hours']);
            
            // Setup committee if needed
            if (isset($step['committee']) && $step['committee']) {
                $this->setupCommitteeVoting($step_id, $step, $context);
            }
        }
    }
    
    /**
     * Find appropriate approver for a step
     */
    private function findApprover($step, $context) {
        // Check for delegation first
        $delegated_to = $this->checkDelegation($step['role'], $context);
        if ($delegated_to) {
            return $delegated_to;
        }
        
        // Find user with required role
        $sql = "
            SELECT id FROM users 
            WHERE role = ? 
            AND is_active = TRUE
            AND (department = ? OR ? IS NULL)
            ORDER BY id ASC
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $step['role'],
            $context['department'] ?? null,
            $context['department'] ?? null
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    }
    
    /**
     * Check for active delegations
     */
    private function checkDelegation($required_role, $context) {
        $sql = "
            SELECT ad.delegate_id
            FROM approval_delegations ad
            JOIN users u ON ad.delegator_id = u.id
            WHERE u.role = ?
            AND ad.is_active = TRUE
            AND CURDATE() BETWEEN ad.start_date AND COALESCE(ad.end_date, CURDATE())
            AND (
                ad.delegation_scope = 'all'
                OR (ad.delegation_scope = 'department' AND ad.department = ?)
                OR (ad.delegation_scope = 'salary_range' AND ? BETWEEN ad.salary_min AND ad.salary_max)
            )
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $required_role,
            $context['department'] ?? null,
            $context['salary'] ?? 0
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['delegate_id'] : null;
    }
    
    /**
     * Start next approval step
     */
    private function startNextStep($instance_id) {
        $sql = "
            SELECT * FROM approval_steps 
            WHERE instance_id = ? AND status = 'pending'
            ORDER BY step_number ASC
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$instance_id]);
        $step = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($step) {
            // Send notification to approver
            $this->sendApprovalNotification($step);
            
            // Update instance current step
            $update_sql = "UPDATE approval_instances SET current_step = ? WHERE id = ?";
            $update_stmt = $this->conn->prepare($update_sql);
            $update_stmt->execute([$step['step_number'], $instance_id]);
        }
    }
    
    /**
     * Process approval decision
     */
    public function processApproval($step_id, $decision, $comments = '', $user_id = null) {
        $user_id = $user_id ?? $_SESSION['user_id'];
        
        try {
            $this->conn->beginTransaction();
            
            // Get step details
            $step = $this->getApprovalStep($step_id);
            if (!$step) {
                throw new Exception("Approval step not found");
            }
            
            // Validate permission
            if (!$this->canApprove($step, $user_id)) {
                throw new Exception("User not authorized to approve this step");
            }
            
            // Update step
            $this->updateApprovalStep($step_id, $decision, $comments, $user_id);
            
            // Update SLA tracking
            $this->completeSLATracking($step_id, $decision === 'approved');
            
            // Process next steps
            if ($decision === 'approved') {
                $this->processNextStep($step['instance_id']);
            } else {
                $this->rejectApproval($step['instance_id'], $comments);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Approval processing error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check if user can approve step
     */
    private function canApprove($step, $user_id) {
        return $step['assigned_to'] == $user_id || 
               $step['delegated_to'] == $user_id ||
               $step['backup_approver_id'] == $user_id;
    }
    
    /**
     * Update approval step
     */
    private function updateApprovalStep($step_id, $decision, $comments, $user_id) {
        $sql = "
            UPDATE approval_steps 
            SET status = ?, decision_date = NOW(), comments = ?
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$decision, $comments, $step_id]);
        
        // Log activity
        if (function_exists('logActivity')) {
            logActivity($user_id, "approval_{$decision}", 'approval_step', $step_id, $comments);
        }
    }
    
    /**
     * Process next step or complete approval
     */
    private function processNextStep($instance_id) {
        // Check if more steps pending
        $sql = "
            SELECT COUNT(*) as pending_count
            FROM approval_steps 
            WHERE instance_id = ? AND status = 'pending'
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$instance_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['pending_count'] == 0) {
            // All steps approved - complete approval
            $this->completeApproval($instance_id);
        } else {
            // Start next step
            $this->startNextStep($instance_id);
        }
    }
    
    /**
     * Complete approval workflow
     */
    private function completeApproval($instance_id) {
        $sql = "
            UPDATE approval_instances 
            SET overall_status = 'approved', completed_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$instance_id]);
        
        // Update entity status
        $this->updateEntityStatus($instance_id, 'approved');
        
        // Send completion notifications
        $this->sendCompletionNotification($instance_id, 'approved');
    }
    
    /**
     * Reject approval workflow
     */
    private function rejectApproval($instance_id, $reason) {
        $sql = "
            UPDATE approval_instances 
            SET overall_status = 'rejected', completed_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$instance_id]);
        
        // Update entity status
        $this->updateEntityStatus($instance_id, 'rejected');
        
        // Send rejection notifications
        $this->sendCompletionNotification($instance_id, 'rejected');
    }
    
    /**
     * Update entity status based on approval result
     */
    private function updateEntityStatus($instance_id, $result) {
        $instance = $this->getApprovalInstance($instance_id);
        
        switch ($instance['entity_type']) {
            case 'offer':
                $status = $result === 'approved' ? 'sent' : 'rejected';
                $sql = "UPDATE offers SET status = ? WHERE id = ?";
                break;
                
            case 'interview':
                $status = $result === 'approved' ? 'approved' : 'rejected';
                $sql = "UPDATE interviews SET approval_status = ? WHERE id = ?";
                break;
                
            case 'role_transition':
                $status = $result === 'approved' ? 'approved' : 'rejected';
                $sql = "UPDATE role_transitions SET transition_status = ? WHERE id = ?";
                break;
                
            default:
                return;
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$status, $instance['entity_id']]);
    }
    
    /**
     * Setup committee voting
     */
    private function setupCommitteeVoting($step_id, $step_config, $context) {
        // Find committee members based on role and department
        $sql = "
            SELECT id FROM users 
            WHERE role IN ('executive', 'director', 'department_head')
            AND is_active = TRUE
            ORDER BY FIELD(role, 'executive', 'director', 'department_head')
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($members as $member) {
            $vote_sql = "
                INSERT INTO committee_votes (approval_step_id, committee_member_id, weight)
                VALUES (?, ?, ?)
            ";
            
            $vote_stmt = $this->conn->prepare($vote_sql);
            $vote_stmt->execute([$step_id, $member['id'], 1.0]);
        }
    }
    
    /**
     * Create SLA tracking
     */
    private function createSLATracking($step_id, $sla_hours) {
        $sql = "
            INSERT INTO approval_sla_tracking (approval_step_id, sla_target_hours)
            VALUES (?, ?)
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$step_id, $sla_hours]);
    }
    
    /**
     * Complete SLA tracking
     */
    private function completeSLATracking($step_id, $approved) {
        $sql = "
            UPDATE approval_sla_tracking 
            SET completed_at = NOW(),
                sla_met = (TIMESTAMPDIFF(HOUR, started_at, NOW()) <= sla_target_hours),
                hours_taken = TIMESTAMPDIFF(HOUR, started_at, NOW())
            WHERE approval_step_id = ?
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$step_id]);
    }
    
    /**
     * Get approval instance
     */
    public function getApprovalInstance($instance_id) {
        $sql = "SELECT * FROM approval_instances WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$instance_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get approval step
     */
    public function getApprovalStep($step_id) {
        $sql = "SELECT * FROM approval_steps WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$step_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Send approval notification
     */
    private function sendApprovalNotification($step) {
        // Implementation for sending email/notification
        // This would integrate with your notification system
    }
    
    /**
     * Send completion notification
     */
    private function sendCompletionNotification($instance_id, $result) {
        // Implementation for sending completion notifications
        // This would integrate with your notification system
    }
    
    /**
     * Check for overdue approvals and escalate
     */
    public function processEscalations() {
        $sql = "
            SELECT ast.*, astp.* 
            FROM approval_sla_tracking ast
            JOIN approval_steps astp ON ast.approval_step_id = astp.id
            WHERE astp.status = 'pending'
            AND ast.escalation_triggered_at IS NULL
            AND ast.started_at < DATE_SUB(NOW(), INTERVAL ast.sla_target_hours HOUR)
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $overdue_steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($overdue_steps as $step) {
            $this->escalateApproval($step['approval_step_id']);
        }
    }
    
    /**
     * Escalate overdue approval
     */
    private function escalateApproval($step_id) {
        // Find escalation target (manager of assigned approver)
        $escalation_target = $this->findEscalationTarget($step_id);
        
        if ($escalation_target) {
            // Update step
            $sql = "
                UPDATE approval_steps 
                SET status = 'escalated', escalated_to = ?
                WHERE id = ?
            ";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$escalation_target, $step_id]);
            
            // Update SLA tracking
            $sla_sql = "
                UPDATE approval_sla_tracking 
                SET escalation_triggered_at = NOW(), escalated_to = ?
                WHERE approval_step_id = ?
            ";
            
            $sla_stmt = $this->conn->prepare($sla_sql);
            $sla_stmt->execute([$escalation_target, $step_id]);
            
            // Send escalation notification
            $this->sendEscalationNotification($step_id, $escalation_target);
        }
    }
    
    /**
     * Find escalation target
     */
    private function findEscalationTarget($step_id) {
        // Simple implementation - find admin or director
        $sql = "
            SELECT id FROM users 
            WHERE role IN ('admin', 'director') 
            AND is_active = TRUE
            ORDER BY FIELD(role, 'admin', 'director')
            LIMIT 1
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['id'] : null;
    }
    
    /**
     * Send escalation notification
     */
    private function sendEscalationNotification($step_id, $escalation_target) {
        // Implementation for escalation notifications
    }
    
    /**
     * Get pending approvals for user
     */
    public function getPendingApprovals($user_id) {
        $sql = "
            SELECT ai.*, astp.*, 
                   CASE ai.entity_type
                       WHEN 'offer' THEN CONCAT(c.first_name, ' ', c.last_name, ' - ', j.title)
                       WHEN 'interview' THEN CONCAT('Interview with ', c2.first_name, ' ', c2.last_name)
                       WHEN 'role_transition' THEN CONCAT('Role transition for ', u.first_name, ' ', u.last_name)
                       ELSE CONCAT(ai.entity_type, ' #', ai.entity_id)
                   END as entity_description
            FROM approval_instances ai
            JOIN approval_steps astp ON ai.id = astp.instance_id
            LEFT JOIN offers o ON ai.entity_type = 'offer' AND ai.entity_id = o.id
            LEFT JOIN candidates c ON o.candidate_id = c.id
            LEFT JOIN job_postings j ON o.job_id = j.id
            LEFT JOIN interviews i ON ai.entity_type = 'interview' AND ai.entity_id = i.id
            LEFT JOIN candidates c2 ON i.candidate_id = c2.id
            LEFT JOIN role_transitions rt ON ai.entity_type = 'role_transition' AND ai.entity_id = rt.id
            LEFT JOIN users u ON rt.employee_id = u.id
            WHERE astp.assigned_to = ? 
            AND astp.status = 'pending'
            AND ai.overall_status = 'pending'
            ORDER BY astp.due_date ASC
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get approval analytics
     */
    public function getApprovalAnalytics($date_from = null, $date_to = null) {
        $date_from = $date_from ?? date('Y-m-d', strtotime('-30 days'));
        $date_to = $date_to ?? date('Y-m-d');
        
        $sql = "
            SELECT 
                ai.entity_type,
                COUNT(*) as total_approvals,
                AVG(TIMESTAMPDIFF(HOUR, ai.initiated_at, ai.completed_at)) as avg_completion_hours,
                SUM(CASE WHEN ai.overall_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN ai.overall_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN ai.overall_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                AVG(CASE WHEN ast.sla_met = 1 THEN 100 ELSE 0 END) as sla_compliance_rate
            FROM approval_instances ai
            LEFT JOIN approval_steps astp ON ai.id = astp.instance_id
            LEFT JOIN approval_sla_tracking ast ON astp.id = ast.approval_step_id
            WHERE DATE(ai.initiated_at) BETWEEN ? AND ?
            GROUP BY ai.entity_type
        ";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$date_from, $date_to]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
