<?php
require_once '../config/config.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    echo "<h2>Setting up Performance Management System Database...</h2>";
    
    // Read and execute the schema
    $schema = file_get_contents('../performance_schema.sql');
    $statements = explode(';', $schema);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $conn->exec($statement);
                echo "<p>✓ Executed: " . substr($statement, 0, 50) . "...</p>";
            } catch (PDOException $e) {
                echo "<p>⚠ Warning: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<h3>Creating sample performance competencies...</h3>";
    
    // Insert sample competencies
    $competencies = [
        [
            'name' => 'Communication Skills',
            'description' => 'Ability to communicate effectively with team members, stakeholders, and clients',
            'category' => 'communication',
            'levels' => json_encode([
                '1' => 'Basic - Can communicate simple ideas clearly',
                '2' => 'Developing - Communicates effectively in most situations',
                '3' => 'Proficient - Strong communication skills across various contexts',
                '4' => 'Advanced - Excellent communicator who can handle complex situations',
                '5' => 'Expert - Outstanding communication leader who mentors others'
            ])
        ],
        [
            'name' => 'Problem Solving',
            'description' => 'Ability to identify, analyze, and resolve problems effectively',
            'category' => 'problem_solving',
            'levels' => json_encode([
                '1' => 'Basic - Can identify simple problems',
                '2' => 'Developing - Can solve routine problems independently',
                '3' => 'Proficient - Effectively solves complex problems',
                '4' => 'Advanced - Innovative problem solver who finds creative solutions',
                '5' => 'Expert - Exceptional problem-solving skills, mentors others'
            ])
        ],
        [
            'name' => 'Leadership',
            'description' => 'Ability to guide, motivate, and influence others effectively',
            'category' => 'leadership',
            'levels' => json_encode([
                '1' => 'Basic - Shows leadership potential in small tasks',
                '2' => 'Developing - Can lead small teams or projects',
                '3' => 'Proficient - Effective leader in most situations',
                '4' => 'Advanced - Strong leader who develops others',
                '5' => 'Expert - Exceptional leader who transforms organizations'
            ])
        ],
        [
            'name' => 'Teamwork & Collaboration',
            'description' => 'Ability to work effectively with others towards common goals',
            'category' => 'teamwork',
            'levels' => json_encode([
                '1' => 'Basic - Participates in team activities',
                '2' => 'Developing - Good team player in most situations',
                '3' => 'Proficient - Strong collaborator who facilitates teamwork',
                '4' => 'Advanced - Excellent team player who builds high-performing teams',
                '5' => 'Expert - Outstanding collaborator who creates collaborative culture'
            ])
        ],
        [
            'name' => 'Technical Proficiency',
            'description' => 'Technical skills and knowledge relevant to the role',
            'category' => 'technical',
            'levels' => json_encode([
                '1' => 'Basic - Has foundational technical knowledge',
                '2' => 'Developing - Can perform most technical tasks independently',
                '3' => 'Proficient - Strong technical skills with good troubleshooting ability',
                '4' => 'Advanced - Expert-level technical skills, can mentor others',
                '5' => 'Expert - Technical authority who drives innovation'
            ])
        ]
    ];
    
    $competency_stmt = $conn->prepare("
        INSERT INTO performance_competencies 
        (competency_name, competency_description, competency_category, proficiency_levels, created_by) 
        VALUES (?, ?, ?, ?, 1)
    ");
    
    foreach ($competencies as $comp) {
        $competency_stmt->execute([
            $comp['name'],
            $comp['description'],
            $comp['category'],
            $comp['levels']
        ]);
        echo "<p>✓ Created competency: {$comp['name']}</p>";
    }
    
    echo "<h3>Creating sample performance templates...</h3>";
    
    // Create sample review template
    $review_template = [
        'sections' => [
            [
                'title' => 'Goal Achievement',
                'type' => 'goals_review',
                'weight' => 40,
                'description' => 'Review progress on goals set for this period'
            ],
            [
                'title' => 'Competency Assessment',
                'type' => 'competencies',
                'weight' => 40,
                'description' => 'Rate performance on key competencies'
            ],
            [
                'title' => 'Overall Performance',
                'type' => 'overall_assessment',
                'weight' => 20,
                'description' => 'Overall performance summary and rating'
            ]
        ],
        'rating_scale' => [
            '1' => 'Does not meet expectations',
            '2' => 'Partially meets expectations',
            '3' => 'Meets expectations',
            '4' => 'Exceeds expectations',
            '5' => 'Far exceeds expectations'
        ]
    ];
    
    $template_stmt = $conn->prepare("
        INSERT INTO performance_templates 
        (template_name, template_type, template_description, template_content, is_default, created_by) 
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    
    $template_stmt->execute([
        'Standard Annual Review',
        'review',
        'Standard template for annual performance reviews',
        json_encode($review_template),
        true
    ]);
    
    echo "<p>✓ Created standard review template</p>";
    
    // Create sample development plan template
    $dev_template = [
        'sections' => [
            [
                'title' => 'Career Goals',
                'type' => 'career_objectives',
                'required' => true
            ],
            [
                'title' => 'Skill Development',
                'type' => 'skills_assessment',
                'required' => true
            ],
            [
                'title' => 'Development Activities',
                'type' => 'activities_planning',
                'required' => true
            ],
            [
                'title' => 'Success Metrics',
                'type' => 'measurement',
                'required' => false
            ]
        ],
        'activity_types' => [
            'training', 'mentoring', 'coaching', 'project', 
            'stretch_assignment', 'conference', 'certification', 'reading'
        ]
    ];
    
    $template_stmt->execute([
        'Standard Development Plan',
        'development_plan',
        'Standard template for individual development plans',
        json_encode($dev_template),
        true
    ]);
    
    echo "<p>✓ Created development plan template</p>";
    
    echo "<h3>Creating sample performance cycle...</h3>";
    
    // Create current year performance cycle
    $current_year = date('Y');
    $cycle_stmt = $conn->prepare("
        INSERT INTO performance_cycles 
        (cycle_name, cycle_type, cycle_year, start_date, end_date, review_deadline, 
         status, description, is_360_enabled, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $cycle_stmt->execute([
        "$current_year Annual Performance Review",
        'annual',
        $current_year,
        "$current_year-01-01",
        "$current_year-12-31",
        "$current_year-12-31",
        'active',
        "Annual performance review cycle for $current_year",
        false
    ]);
    
    echo "<p>✓ Created $current_year performance cycle</p>";
    
    echo "<h3>Performance Management System setup completed successfully!</h3>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Create performance directory: <code>mkdir performance</code></li>";
    echo "<li>Set up individual modules for goals, reviews, feedback, etc.</li>";
    echo "<li>Configure role-based permissions for performance management</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<h3>Error setting up Performance Management System:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and permissions.</p>";
}
?> 