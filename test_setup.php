<?php
/**
 * Test Data Setup for Interview Management System
 * This script creates sample data for testing all interview features
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

echo "<h1>HR Operations - Test Data Setup</h1>";
echo "<p>Setting up test data for Interview Management System...</p>";

$db = new Database();
$conn = $db->getConnection();

try {
    // Check if test data already exists
    $existing_check = $conn->query("SELECT COUNT(*) as count FROM candidates WHERE email LIKE '%test%'")->fetch();
    
    if ($existing_check['count'] > 0) {
        echo "<div style='background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7; margin: 10px 0;'>";
        echo "<strong>Warning:</strong> Test data already exists. <a href='?reset=1'>Click here to reset and recreate</a>";
        echo "</div>";
        
        if (!isset($_GET['reset'])) {
            exit;
        } else {
            // Clean up existing test data
            $conn->exec("DELETE FROM interview_feedback WHERE interview_id IN (SELECT id FROM interviews WHERE candidate_id IN (SELECT id FROM candidates WHERE email LIKE '%test%'))");
            $conn->exec("DELETE FROM interviews WHERE candidate_id IN (SELECT id FROM candidates WHERE email LIKE '%test%'))");
            $conn->exec("DELETE FROM candidates WHERE email LIKE '%test%'");
            $conn->exec("DELETE FROM job_postings WHERE title LIKE '%Test%'");
            echo "<p>✅ Cleaned up existing test data</p>";
        }
    }

    // Create test job postings
    echo "<h3>Creating Test Job Postings...</h3>";
    
    $jobs = [
        [
            'title' => 'Senior Software Engineer - Test',
            'description' => 'We are looking for a senior software engineer to join our development team.',
            'requirements' => 'Bachelor\'s degree in Computer Science, 5+ years experience, PHP, JavaScript, MySQL',
            'department' => 'Engineering',
            'location' => 'San Francisco, CA',
            'salary_range' => '$120,000 - $150,000',
            'employment_type' => 'full_time'
        ],
        [
            'title' => 'Marketing Manager - Test',
            'description' => 'Lead our marketing initiatives and drive brand awareness.',
            'requirements' => 'MBA preferred, 3+ years marketing experience, digital marketing expertise',
            'department' => 'Marketing',
            'location' => 'New York, NY',
            'salary_range' => '$80,000 - $100,000',
            'employment_type' => 'full_time'
        ],
        [
            'title' => 'Data Analyst - Test',
            'description' => 'Analyze data to provide insights for business decisions.',
            'requirements' => 'Bachelor\'s in Statistics/Math, SQL, Python, Tableau experience',
            'department' => 'Analytics',
            'location' => 'Remote',
            'salary_range' => '$70,000 - $90,000',
            'employment_type' => 'full_time'
        ],
        [
            'title' => 'UX Designer - Test',
            'description' => 'Design user-friendly interfaces and improve user experience.',
            'requirements' => 'Design degree, Figma, Sketch, 2+ years UX experience',
            'department' => 'Design',
            'location' => 'Los Angeles, CA',
            'salary_range' => '$75,000 - $95,000',
            'employment_type' => 'full_time'
        ]
    ];

    $job_ids = [];
    foreach ($jobs as $job) {
        $stmt = $conn->prepare("
            INSERT INTO job_postings (title, description, requirements, department, location, salary_range, employment_type, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $job['title'], $job['description'], $job['requirements'], 
            $job['department'], $job['location'], $job['salary_range'], $job['employment_type']
        ]);
        $job_ids[] = $conn->lastInsertId();
        echo "✅ Created job: {$job['title']}<br>";
    }

    // Create test candidates
    echo "<h3>Creating Test Candidates...</h3>";
    
    $candidates = [
        [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john.smith@test.com',
            'phone' => '+1-555-0101',
            'linkedin_url' => 'https://linkedin.com/in/johnsmith',
            'skills' => 'PHP, JavaScript, MySQL, React, Node.js',
            'experience_years' => 6,
            'current_location' => 'San Francisco, CA',
            'status' => 'interviewing',
            'source' => 'LinkedIn',
            'applied_for' => 0, // Will be set to first job
            'assigned_to' => 1
        ],
        [
            'first_name' => 'Sarah',
            'last_name' => 'Johnson',
            'email' => 'sarah.johnson@test.com',
            'phone' => '+1-555-0102',
            'linkedin_url' => 'https://linkedin.com/in/sarahjohnson',
            'skills' => 'Digital Marketing, SEO, SEM, Analytics, Content Strategy',
            'experience_years' => 4,
            'current_location' => 'New York, NY',
            'status' => 'interviewing',
            'source' => 'Company Website',
            'applied_for' => 1, // Marketing Manager
            'assigned_to' => 1
        ],
        [
            'first_name' => 'Michael',
            'last_name' => 'Davis',
            'email' => 'michael.davis@test.com',
            'phone' => '+1-555-0103',
            'linkedin_url' => 'https://linkedin.com/in/michaeldavis',
            'skills' => 'Python, SQL, Tableau, R, Statistics, Machine Learning',
            'experience_years' => 3,
            'current_location' => 'Chicago, IL',
            'status' => 'interviewing',
            'source' => 'Indeed',
            'applied_for' => 2, // Data Analyst
            'assigned_to' => 1
        ],
        [
            'first_name' => 'Emily',
            'last_name' => 'Chen',
            'email' => 'emily.chen@test.com',
            'phone' => '+1-555-0104',
            'linkedin_url' => 'https://linkedin.com/in/emilychen',
            'skills' => 'UX Design, Figma, Sketch, Prototyping, User Research',
            'experience_years' => 3,
            'current_location' => 'Los Angeles, CA',
            'status' => 'interviewing',
            'source' => 'Referral',
            'applied_for' => 3, // UX Designer
            'assigned_to' => 1
        ],
        [
            'first_name' => 'David',
            'last_name' => 'Wilson',
            'email' => 'david.wilson@test.com',
            'phone' => '+1-555-0105',
            'linkedin_url' => 'https://linkedin.com/in/davidwilson',
            'skills' => 'Full Stack Development, React, Angular, PHP, Laravel',
            'experience_years' => 8,
            'current_location' => 'Austin, TX',
            'status' => 'shortlisted',
            'source' => 'Glassdoor',
            'applied_for' => 0, // Senior Software Engineer
            'assigned_to' => 1
        ],
        [
            'first_name' => 'Lisa',
            'last_name' => 'Anderson',
            'email' => 'lisa.anderson@test.com',
            'phone' => '+1-555-0106',
            'linkedin_url' => 'https://linkedin.com/in/lisaanderson',
            'skills' => 'Brand Management, Campaign Strategy, Social Media, Analytics',
            'experience_years' => 5,
            'current_location' => 'Boston, MA',
            'status' => 'new',
            'source' => 'LinkedIn',
            'applied_for' => 1, // Marketing Manager
            'assigned_to' => 1
        ]
    ];

    $candidate_ids = [];
    foreach ($candidates as $candidate) {
        $applied_for = $candidate['applied_for'] < count($job_ids) ? $job_ids[$candidate['applied_for']] : $job_ids[0];
        
        $stmt = $conn->prepare("
            INSERT INTO candidates (first_name, last_name, email, phone, linkedin_url, skills, experience_years, current_location, status, source, applied_for, assigned_to)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $candidate['first_name'], $candidate['last_name'], $candidate['email'], 
            $candidate['phone'], $candidate['linkedin_url'], $candidate['skills'],
            $candidate['experience_years'], $candidate['current_location'], 
            $candidate['status'], $candidate['source'], $applied_for, $candidate['assigned_to']
        ]);
        $candidate_ids[] = $conn->lastInsertId();
        echo "✅ Created candidate: {$candidate['first_name']} {$candidate['last_name']}<br>";
    }

    // Create test interviews with various statuses and dates
    echo "<h3>Creating Test Interviews...</h3>";
    
    $interview_data = [
        // Today's interviews
        [
            'candidate_id' => $candidate_ids[0],
            'job_id' => $job_ids[0],
            'interviewer_id' => 1,
            'interview_type' => 'video',
            'scheduled_date' => date('Y-m-d H:i:s', strtotime('today +2 hours')),
            'duration' => 60,
            'location' => 'Virtual Meeting',
            'meeting_link' => 'https://zoom.us/j/1234567890',
            'status' => 'scheduled',
            'notes' => 'Technical interview focusing on PHP and database design'
        ],
        [
            'candidate_id' => $candidate_ids[1],
            'job_id' => $job_ids[1],
            'interviewer_id' => 1,
            'interview_type' => 'in_person',
            'scheduled_date' => date('Y-m-d H:i:s', strtotime('today +4 hours')),
            'duration' => 45,
            'location' => 'Conference Room A, 5th Floor',
            'meeting_link' => '',
            'status' => 'scheduled',
            'notes' => 'Marketing strategy discussion and portfolio review'
        ],
        
        // Tomorrow's interviews
        [
            'candidate_id' => $candidate_ids[2],
            'job_id' => $job_ids[2],
            'interviewer_id' => 1,
            'interview_type' => 'phone',
            'scheduled_date' => date('Y-m-d H:i:s', strtotime('tomorrow +1 hour')),
            'duration' => 30,
            'location' => 'Phone Interview',
            'meeting_link' => '',
            'status' => 'scheduled',
            'notes' => 'Initial screening call'
        ],
        [
            'candidate_id' => $candidate_ids[3],
            'job_id' => $job_ids[3],
            'interviewer_id' => 1,
            'interview_type' => 'video',
            'scheduled_date' => date('Y-m-d H:i:s', strtotime('tomorrow +3 hours')),
            'duration' => 60,
            'location' => 'Virtual Meeting',
            'meeting_link' => 'https://meet.google.com/abc-defg-hij',
            'status' => 'scheduled',
            'notes' => 'Design portfolio presentation and team fit discussion'
        ],
        
        // Completed interviews (for feedback testing)
        [
            'candidate_id' => $candidate_ids[4],
            'job_id' => $job_ids[0],
            'interviewer_id' => 1,
            'interview_type' => 'technical',
            'scheduled_date' => date('Y-m-d H:i:s', strtotime('yesterday')),
            'duration' => 90,
            'location' => 'Lab Room 2',
            'meeting_link' => '',
            'status' => 'completed',
            'notes' => 'Coding challenge and system design discussion'
        ],
        [
            'candidate_id' => $candidate_ids[5],
            'job_id' => $job_ids[1],
            'interviewer_id' => 1,
            'interview_type' => 'in_person',
            'scheduled_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'duration' => 60,
            'location' => 'Conference Room B',
            'meeting_link' => '',
            'status' => 'completed',
            'notes' => 'Final round interview with team leads'
        ],
        
        // Future interviews
        [
            'candidate_id' => $candidate_ids[0],
            'job_id' => $job_ids[0],
            'interviewer_id' => 1,
            'interview_type' => 'in_person',
            'scheduled_date' => date('Y-m-d H:i:s', strtotime('+3 days +2 hours')),
            'duration' => 60,
            'location' => 'Executive Conference Room',
            'meeting_link' => '',
            'status' => 'scheduled',
            'notes' => 'Final interview with CTO'
        ],
        
        // Overdue interview (for testing alerts)
        [
            'candidate_id' => $candidate_ids[2],
            'job_id' => $job_ids[2],
            'interviewer_id' => 1,
            'interview_type' => 'video',
            'scheduled_date' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'duration' => 45,
            'location' => 'Virtual Meeting',
            'meeting_link' => 'https://teams.microsoft.com/l/meetup-join/19%3a...',
            'status' => 'scheduled',
            'notes' => 'Follow-up technical discussion'
        ]
    ];

    $interview_ids = [];
    foreach ($interview_data as $interview) {
        $stmt = $conn->prepare("
            INSERT INTO interviews (candidate_id, job_id, interviewer_id, interview_type, scheduled_date, duration, location, meeting_link, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $interview['candidate_id'], $interview['job_id'], $interview['interviewer_id'],
            $interview['interview_type'], $interview['scheduled_date'], $interview['duration'],
            $interview['location'], $interview['meeting_link'], $interview['status'], $interview['notes']
        ]);
        $interview_ids[] = $conn->lastInsertId();
        echo "✅ Created interview: " . date('M j, Y g:i A', strtotime($interview['scheduled_date'])) . " - " . ucfirst($interview['interview_type']) . "<br>";
    }

    // Create sample feedback for completed interviews
    echo "<h3>Creating Sample Interview Feedback...</h3>";
    
    $feedback_data = [
        [
            'interview_id' => $interview_ids[4], // David Wilson's completed interview
            'interviewer_id' => 1,
            'technical_rating' => 4,
            'communication_rating' => 5,
            'cultural_fit_rating' => 4,
            'overall_rating' => 4,
            'strengths' => 'Excellent problem-solving skills, clean code structure, and good understanding of design patterns. Shows strong leadership experience and technical depth.',
            'weaknesses' => 'Could improve knowledge of newer frameworks, but shows willingness to learn. Minor gaps in system design at scale.',
            'recommendation' => 'hire',
            'feedback_notes' => 'David demonstrated strong technical skills during the coding challenge. He approached problems methodically and wrote clean, well-commented code. His experience leading teams is evident in how he thinks about code maintainability and team collaboration. I would recommend moving forward with the hiring process.'
        ],
        [
            'interview_id' => $interview_ids[5], // Lisa Anderson's completed interview
            'interviewer_id' => 1,
            'technical_rating' => 3,
            'communication_rating' => 5,
            'cultural_fit_rating' => 5,
            'overall_rating' => 4,
            'strengths' => 'Outstanding communication skills, proven track record in campaign management, and excellent cultural alignment with our values. Very collaborative approach.',
            'weaknesses' => 'Limited experience with some of our specific tools, but expressed enthusiasm to learn. Could benefit from more data analytics background.',
            'recommendation' => 'hire',
            'feedback_notes' => 'Lisa impressed the entire interview panel with her strategic thinking and communication skills. Her campaign portfolio shows creativity and results-driven approach. She would fit well with our marketing team culture and bring valuable experience in brand management.'
        ]
    ];

    foreach ($feedback_data as $feedback) {
        $stmt = $conn->prepare("
            INSERT INTO interview_feedback (interview_id, interviewer_id, technical_rating, communication_rating, cultural_fit_rating, overall_rating, strengths, weaknesses, recommendation, feedback_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $feedback['interview_id'], $feedback['interviewer_id'], $feedback['technical_rating'],
            $feedback['communication_rating'], $feedback['cultural_fit_rating'], $feedback['overall_rating'],
            $feedback['strengths'], $feedback['weaknesses'], $feedback['recommendation'], $feedback['feedback_notes']
        ]);
        echo "✅ Created feedback for interview ID: {$feedback['interview_id']}<br>";
    }

    // Add some sample interview questions
    echo "<h3>Creating Sample Interview Questions...</h3>";
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS interview_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(100) NOT NULL,
            question_type ENUM('behavioral', 'technical', 'situational', 'general') NOT NULL,
            difficulty_level ENUM('entry', 'intermediate', 'senior', 'expert') NOT NULL,
            question TEXT NOT NULL,
            suggested_answer TEXT,
            follow_up_questions TEXT,
            tags VARCHAR(255),
            department VARCHAR(100),
            is_active BOOLEAN DEFAULT TRUE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");

    $questions = [
        [
            'category' => 'Problem Solving',
            'question_type' => 'behavioral',
            'difficulty_level' => 'intermediate',
            'question' => 'Tell me about a time when you had to solve a complex problem with limited resources.',
            'suggested_answer' => 'Look for: specific situation, actions taken, creative solutions, measurable outcomes',
            'follow_up_questions' => 'What would you do differently? How did you prioritize your approach?',
            'tags' => 'problem-solving, resourcefulness, creativity',
            'department' => 'Engineering'
        ],
        [
            'category' => 'Technical Skills',
            'question_type' => 'technical',
            'difficulty_level' => 'senior',
            'question' => 'How would you design a scalable web application that can handle 1 million concurrent users?',
            'suggested_answer' => 'Should cover: load balancing, database sharding, caching strategies, CDNs, microservices',
            'follow_up_questions' => 'How would you handle database failures? What monitoring would you implement?',
            'tags' => 'system-design, scalability, architecture',
            'department' => 'Engineering'
        ],
        [
            'category' => 'Teamwork',
            'question_type' => 'behavioral',
            'difficulty_level' => 'entry',
            'question' => 'Describe a situation where you had to work with a difficult team member.',
            'suggested_answer' => 'Look for: communication skills, conflict resolution, professional approach',
            'follow_up_questions' => 'What was the outcome? What did you learn from this experience?',
            'tags' => 'teamwork, communication, conflict-resolution',
            'department' => 'General'
        ]
    ];

    foreach ($questions as $question) {
        $stmt = $conn->prepare("
            INSERT INTO interview_questions (category, question_type, difficulty_level, question, suggested_answer, follow_up_questions, tags, department, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $question['category'], $question['question_type'], $question['difficulty_level'],
            $question['question'], $question['suggested_answer'], $question['follow_up_questions'],
            $question['tags'], $question['department']
        ]);
        echo "✅ Created question: " . substr($question['question'], 0, 50) . "...<br>";
    }

    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; margin: 20px 0; border-radius: 5px;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>✅ Test Data Setup Complete!</h3>";
    echo "<p style='color: #155724; margin-bottom: 0;'>Successfully created:</p>";
    echo "<ul style='color: #155724;'>";
    echo "<li>" . count($jobs) . " Test Job Postings</li>";
    echo "<li>" . count($candidates) . " Test Candidates</li>";
    echo "<li>" . count($interview_data) . " Test Interviews (various statuses and dates)</li>";
    echo "<li>" . count($feedback_data) . " Sample Interview Feedback</li>";
    echo "<li>" . count($questions) . " Sample Interview Questions</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; margin: 20px 0; border-radius: 5px;'>";
    echo "<h3 style='color: #856404; margin-top: 0;'>🧪 Ready for Testing!</h3>";
    echo "<p style='color: #856404;'>You can now test the following features:</p>";
    echo "<ul style='color: #856404;'>";
    echo "<li><strong>Today's Interviews:</strong> 2 interviews scheduled for today</li>";
    echo "<li><strong>Interview Scheduling:</strong> Try scheduling new interviews</li>";
    echo "<li><strong>Interview Feedback:</strong> 2 completed interviews need feedback</li>";
    echo "<li><strong>Calendar View:</strong> See all interviews across different days</li>";
    echo "<li><strong>Reminder System:</strong> Test sending reminders</li>";
    echo "<li><strong>Question Bank:</strong> Sample questions available</li>";
    echo "<li><strong>Analytics:</strong> View interview statistics and reports</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div style='margin: 20px 0;'>";
    echo "<h3>Quick Testing Links:</h3>";
    echo "<p><a href='dashboard.php' style='color: #007bff;'>🏠 Go to Dashboard</a></p>";
    echo "<p><a href='interviews/list.php' style='color: #007bff;'>📋 View All Interviews</a></p>";
    echo "<p><a href='interviews/today.php' style='color: #007bff;'>📅 Today's Interviews</a></p>";
    echo "<p><a href='interviews/schedule.php' style='color: #007bff;'>➕ Schedule New Interview</a></p>";
    echo "<p><a href='interviews/calendar.php' style='color: #007bff;'>📆 Calendar View</a></p>";
    echo "<p><a href='interviews/feedback.php' style='color: #007bff;'>📝 Submit Feedback</a></p>";
    echo "<p><a href='interviews/reports.php' style='color: #007bff;'>📊 View Reports</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; margin: 20px 0; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24; margin-top: 0;'>❌ Error Setting Up Test Data</h3>";
    echo "<p style='color: #721c24;'>Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}
?> 