<?php
/**
 * Interview Statistics and Analytics Module
 * Provides comprehensive interview metrics and reporting data
 */

class InterviewStatistics {
    private $conn;
    
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }
    
    /**
     * Get overall interview statistics
     */
    public function getOverallStats($period = '30_days') {
        $date_condition = $this->getDateCondition($period);
        
        $stmt = $this->conn->query("
            SELECT 
                COUNT(*) as total_interviews,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled,
                SUM(CASE WHEN scheduled_date < NOW() AND status = 'scheduled' THEN 1 ELSE 0 END) as overdue,
                AVG(duration) as avg_duration
            FROM interviews 
            WHERE $date_condition
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get interview success metrics
     */
    public function getSuccessMetrics($period = '30_days') {
        $date_condition = $this->getDateCondition($period);
        
        $stmt = $this->conn->query("
            SELECT 
                COUNT(i.id) as total_with_feedback,
                SUM(CASE WHEN f.recommendation IN ('hire', 'strong_hire') THEN 1 ELSE 0 END) as positive_recommendations,
                SUM(CASE WHEN f.recommendation = 'strong_hire' THEN 1 ELSE 0 END) as strong_hire,
                SUM(CASE WHEN f.recommendation = 'hire' THEN 1 ELSE 0 END) as hire,
                SUM(CASE WHEN f.recommendation = 'neutral' THEN 1 ELSE 0 END) as neutral,
                SUM(CASE WHEN f.recommendation = 'no_hire' THEN 1 ELSE 0 END) as no_hire,
                SUM(CASE WHEN f.recommendation = 'strong_no_hire' THEN 1 ELSE 0 END) as strong_no_hire,
                AVG(f.overall_rating) as avg_overall_rating,
                AVG(f.technical_rating) as avg_technical_rating,
                AVG(f.communication_rating) as avg_communication_rating,
                AVG(f.cultural_fit_rating) as avg_cultural_fit_rating
            FROM interviews i
            JOIN interview_feedback f ON i.id = f.interview_id
            WHERE i.status = 'completed' AND $date_condition
        ");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate success rate
        if ($result['total_with_feedback'] > 0) {
            $result['success_rate'] = round(($result['positive_recommendations'] / $result['total_with_feedback']) * 100, 2);
        } else {
            $result['success_rate'] = 0;
        }
        
        return $result;
    }
    
    /**
     * Get interviewer performance metrics
     */
    public function getInterviewerMetrics($period = '30_days') {
        $date_condition = $this->getDateCondition($period);
        
        $stmt = $this->conn->query("
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                COUNT(i.id) as total_interviews,
                SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as completed_interviews,
                SUM(CASE WHEN i.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_interviews,
                SUM(CASE WHEN f.recommendation IN ('hire', 'strong_hire') THEN 1 ELSE 0 END) as positive_recommendations,
                AVG(f.overall_rating) as avg_rating,
                COUNT(f.id) as feedback_submitted
            FROM users u
            LEFT JOIN interviews i ON u.id = i.interviewer_id AND $date_condition
            LEFT JOIN interview_feedback f ON i.id = f.interview_id
            WHERE u.role IN ('interviewer', 'hiring_manager', 'hr_recruiter', 'admin')
            GROUP BY u.id, u.first_name, u.last_name
            HAVING total_interviews > 0
            ORDER BY total_interviews DESC
        ");
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate additional metrics
        foreach ($results as &$interviewer) {
            $interviewer['completion_rate'] = $interviewer['total_interviews'] > 0 
                ? round(($interviewer['completed_interviews'] / $interviewer['total_interviews']) * 100, 2)
                : 0;
                
            $interviewer['feedback_rate'] = $interviewer['completed_interviews'] > 0
                ? round(($interviewer['feedback_submitted'] / $interviewer['completed_interviews']) * 100, 2)
                : 0;
                
            $interviewer['success_rate'] = $interviewer['feedback_submitted'] > 0
                ? round(($interviewer['positive_recommendations'] / $interviewer['feedback_submitted']) * 100, 2)
                : 0;
        }
        
        return $results;
    }
    
    /**
     * Get interview type distribution
     */
    public function getTypeDistribution($period = '30_days') {
        $date_condition = $this->getDateCondition($period);
        
        $stmt = $this->conn->query("
            SELECT 
                interview_type,
                COUNT(*) as count,
                AVG(duration) as avg_duration,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
            FROM interviews 
            WHERE $date_condition
            GROUP BY interview_type
            ORDER BY count DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get daily interview volume
     */
    public function getDailyVolume($period = '30_days') {
        $date_condition = $this->getDateCondition($period);
        
        $stmt = $this->conn->query("
            SELECT 
                DATE(scheduled_date) as interview_date,
                COUNT(*) as total_interviews,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
            FROM interviews 
            WHERE $date_condition
            GROUP BY DATE(scheduled_date)
            ORDER BY interview_date ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get time slot analysis
     */
    public function getTimeSlotAnalysis($period = '30_days') {
        $date_condition = $this->getDateCondition($period);
        
        $stmt = $this->conn->query("
            SELECT 
                HOUR(scheduled_date) as hour_slot,
                COUNT(*) as interview_count,
                AVG(CASE WHEN f.overall_rating THEN f.overall_rating ELSE NULL END) as avg_rating
            FROM interviews i
            LEFT JOIN interview_feedback f ON i.id = f.interview_id
            WHERE $date_condition
            GROUP BY HOUR(scheduled_date)
            ORDER BY hour_slot ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get department-wise interview statistics
     */
    public function getDepartmentStats($period = '30_days') {
        $date_condition = $this->getDateCondition($period);
        
        $stmt = $this->conn->query("
            SELECT 
                j.department,
                COUNT(i.id) as total_interviews,
                SUM(CASE WHEN i.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN f.recommendation IN ('hire', 'strong_hire') THEN 1 ELSE 0 END) as positive_feedback,
                AVG(f.overall_rating) as avg_rating
            FROM interviews i
            JOIN job_postings j ON i.job_id = j.id
            LEFT JOIN interview_feedback f ON i.id = f.interview_id
            WHERE $date_condition
            GROUP BY j.department
            ORDER BY total_interviews DESC
        ");
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate success rates
        foreach ($results as &$dept) {
            $dept['success_rate'] = $dept['completed'] > 0 
                ? round(($dept['positive_feedback'] / $dept['completed']) * 100, 2)
                : 0;
        }
        
        return $results;
    }
    
    /**
     * Get interview no-show statistics
     */
    public function getNoShowStats($period = '30_days') {
        $date_condition = $this->getDateCondition($period);
        
        $stmt = $this->conn->query("
            SELECT 
                DATE(scheduled_date) as interview_date,
                COUNT(*) as total_scheduled,
                SUM(CASE WHEN scheduled_date < NOW() AND status = 'scheduled' THEN 1 ELSE 0 END) as no_shows
            FROM interviews 
            WHERE $date_condition AND status IN ('scheduled', 'completed')
            GROUP BY DATE(scheduled_date)
            ORDER BY interview_date DESC
        ");
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate no-show rates
        foreach ($results as &$day) {
            $day['no_show_rate'] = $day['total_scheduled'] > 0 
                ? round(($day['no_shows'] / $day['total_scheduled']) * 100, 2)
                : 0;
        }
        
        return $results;
    }
    
    /**
     * Get feedback quality metrics
     */
    public function getFeedbackQuality($period = '30_days') {
        $date_condition = $this->getDateCondition($period);
        
        $stmt = $this->conn->query("
            SELECT 
                COUNT(i.id) as total_completed,
                COUNT(f.id) as feedback_submitted,
                AVG(LENGTH(f.strengths)) as avg_strengths_length,
                AVG(LENGTH(f.weaknesses)) as avg_weaknesses_length,
                AVG(LENGTH(f.feedback_notes)) as avg_notes_length,
                SUM(CASE WHEN LENGTH(f.feedback_notes) > 100 THEN 1 ELSE 0 END) as detailed_feedback_count
            FROM interviews i
            LEFT JOIN interview_feedback f ON i.id = f.interview_id
            WHERE i.status = 'completed' AND $date_condition
        ");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate feedback rate
        $result['feedback_rate'] = $result['total_completed'] > 0 
            ? round(($result['feedback_submitted'] / $result['total_completed']) * 100, 2)
            : 0;
            
        $result['detailed_feedback_rate'] = $result['feedback_submitted'] > 0
            ? round(($result['detailed_feedback_count'] / $result['feedback_submitted']) * 100, 2)
            : 0;
        
        return $result;
    }
    
    /**
     * Get candidate progression statistics
     */
    public function getCandidateProgression($period = '30_days') {
        $date_condition = $this->getDateCondition($period);
        
        $stmt = $this->conn->query("
            SELECT 
                c.status as candidate_status,
                COUNT(DISTINCT i.candidate_id) as candidate_count,
                COUNT(i.id) as total_interviews,
                AVG(interview_counts.interview_count) as avg_interviews_per_candidate
            FROM interviews i
            JOIN candidates c ON i.candidate_id = c.id
            JOIN (
                SELECT candidate_id, COUNT(*) as interview_count
                FROM interviews
                GROUP BY candidate_id
            ) interview_counts ON i.candidate_id = interview_counts.candidate_id
            WHERE $date_condition
            GROUP BY c.status
            ORDER BY candidate_count DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Helper method to get date condition based on period
     */
    private function getDateCondition($period) {
        switch ($period) {
            case '7_days':
                return "scheduled_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30_days':
                return "scheduled_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '90_days':
                return "scheduled_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case '1_year':
                return "scheduled_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            case 'this_month':
                return "MONTH(scheduled_date) = MONTH(CURDATE()) AND YEAR(scheduled_date) = YEAR(CURDATE())";
            case 'last_month':
                return "MONTH(scheduled_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(scheduled_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            default:
                return "scheduled_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        }
    }
    
    /**
     * Generate comprehensive interview report
     */
    public function generateReport($period = '30_days') {
        return [
            'overall_stats' => $this->getOverallStats($period),
            'success_metrics' => $this->getSuccessMetrics($period),
            'interviewer_metrics' => $this->getInterviewerMetrics($period),
            'type_distribution' => $this->getTypeDistribution($period),
            'daily_volume' => $this->getDailyVolume($period),
            'time_slots' => $this->getTimeSlotAnalysis($period),
            'department_stats' => $this->getDepartmentStats($period),
            'no_show_stats' => $this->getNoShowStats($period),
            'feedback_quality' => $this->getFeedbackQuality($period),
            'candidate_progression' => $this->getCandidateProgression($period)
        ];
    }
}

// Helper functions for easy access
function getInterviewStats($period = '30_days') {
    $stats = new InterviewStatistics();
    return $stats->getOverallStats($period);
}

function getInterviewSuccessRate($period = '30_days') {
    $stats = new InterviewStatistics();
    $metrics = $stats->getSuccessMetrics($period);
    return $metrics['success_rate'] ?? 0;
}

function getTopInterviewers($period = '30_days', $limit = 5) {
    $stats = new InterviewStatistics();
    $interviewers = $stats->getInterviewerMetrics($period);
    return array_slice($interviewers, 0, $limit);
}

function getInterviewReport($period = '30_days') {
    $stats = new InterviewStatistics();
    return $stats->generateReport($period);
}
?> 