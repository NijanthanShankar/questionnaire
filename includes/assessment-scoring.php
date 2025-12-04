<?php
/**
 * CleanIndex Portal - Assessment Scoring System
 * Handles assessment grading, score calculation, and grade assignment
 */

if (!defined('ABSPATH')) exit;

// Calculate score from assessment answers
function cip_calculate_assessment_score($assessment_id) {
    global $wpdb;
    $table_assess = $wpdb->prefix . 'company_assessments';
    
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_assess WHERE id = %d",
        $assessment_id
    ), ARRAY_A);
    
    if (!$assessment) {
        return false;
    }
    
    $answers = json_decode($assessment['answers'], true);
    if (!$answers || !is_array($answers)) {
        return 0;
    }
    
    // Get scoring rules from settings
    $scoring_rules = get_option('cip_scoring_rules', []);
    
    $total_questions = count($answers);
    $total_points = 0;
    $max_points = $total_questions * 10; // Each question worth 10 points
    
    foreach ($answers as $question => $answer) {
        // Check if there's a specific rule for this question
        $question_key = sanitize_title($question);
        
        if (isset($scoring_rules[$question_key])) {
            $rule = $scoring_rules[$question_key];
            $points = cip_evaluate_answer($answer, $rule);
            $total_points += $points;
        } else {
            // Default scoring: Yes/implemented = 10, Partial = 5, No = 0
            $points = cip_default_score_answer($answer);
            $total_points += $points;
        }
    }
    
    // Calculate percentage score
    $score = ($total_points / $max_points) * 100;
    $score = round($score, 2);
    
    return $score;
}

// Default answer scoring
function cip_default_score_answer($answer) {
    $answer_lower = strtolower(trim($answer));
    
    // Full score keywords
    $full_score = ['yes', 'fully implemented', 'always', 'complete', 'excellent'];
    foreach ($full_score as $keyword) {
        if (strpos($answer_lower, $keyword) !== false) {
            return 10;
        }
    }
    
    // Partial score keywords
    $partial_score = ['partially', 'sometimes', 'in progress', 'developing'];
    foreach ($partial_score as $keyword) {
        if (strpos($answer_lower, $keyword) !== false) {
            return 5;
        }
    }
    
    // No score keywords
    $no_score = ['no', 'not implemented', 'never', 'none'];
    foreach ($no_score as $keyword) {
        if (strpos($answer_lower, $keyword) !== false) {
            return 0;
        }
    }
    
    // Default: if answer is substantial (more than 50 characters), give partial credit
    if (strlen($answer) > 50) {
        return 7;
    }
    
    return 3; // Minimal credit for any answer
}

// Evaluate answer based on custom rule
function cip_evaluate_answer($answer, $rule) {
    $answer_lower = strtolower(trim($answer));
    
    if (isset($rule['keywords'])) {
        foreach ($rule['keywords'] as $keyword => $points) {
            if (strpos($answer_lower, $keyword) !== false) {
                return intval($points);
            }
        }
    }
    
    return $rule['default'] ?? 5;
}

// Get grade from score
function cip_get_grade_from_score($score) {
    // Get custom grade boundaries or use defaults
    $boundaries = get_option('cip_grade_boundaries', [
        'A+' => 90,
        'A' => 80,
        'B' => 70,
        'C' => 60,
        'D' => 50,
        'F' => 0
    ]);
    
    foreach ($boundaries as $grade => $min_score) {
        if ($score >= $min_score) {
            return $grade;
        }
    }
    
    return 'F';
}

// Get grade details
function cip_get_grade_details($grade) {
    $details = [
        'A+' => [
            'name' => 'Outstanding',
            'description' => 'Exceptional ESG performance across all areas',
            'badge' => 'gold',
            'color' => '#FFD700',
            'icon' => '🥇'
        ],
        'A' => [
            'name' => 'Excellent',
            'description' => 'Strong ESG performance with comprehensive practices',
            'badge' => 'silver',
            'color' => '#C0C0C0',
            'icon' => '🥈'
        ],
        'B' => [
            'name' => 'Very Good',
            'description' => 'Good ESG performance with room for improvement',
            'badge' => 'bronze',
            'color' => '#CD7F32',
            'icon' => '🥉'
        ],
        'C' => [
            'name' => 'Good',
            'description' => 'Acceptable ESG performance, basic practices in place',
            'badge' => 'blue',
            'color' => '#4A90E2',
            'icon' => '🔵'
        ],
        'D' => [
            'name' => 'Satisfactory',
            'description' => 'Minimum ESG performance, significant improvements needed',
            'badge' => 'green',
            'color' => '#7ED321',
            'icon' => '🟢'
        ],
        'F' => [
            'name' => 'Needs Improvement',
            'description' => 'ESG practices below acceptable standards',
            'badge' => 'none',
            'color' => '#FF0000',
            'icon' => '⚠️'
        ]
    ];
    
    return $details[$grade] ?? $details['F'];
}

// Check if user is eligible for certificate
function cip_is_eligible_for_certificate($user_id) {
    global $wpdb;
    $table_assess = $wpdb->prefix . 'company_assessments';
    $table_scores = $wpdb->prefix . 'cip_assessment_scores';
    
    // Check if assessment is complete
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_assess WHERE user_id = %d AND progress >= 5",
        $user_id
    ), ARRAY_A);
    
    if (!$assessment) {
        return ['eligible' => false, 'reason' => 'Assessment not completed'];
    }
    
    // Check if scored
    $score = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_scores WHERE assessment_id = %d",
        $assessment['id']
    ), ARRAY_A);
    
    if (!$score) {
        return ['eligible' => false, 'reason' => 'Assessment not yet scored'];
    }
    
    // Check minimum score
    $min_score = get_option('cip_minimum_certificate_score', 50);
    if ($score['score'] < $min_score) {
        return [
            'eligible' => false,
            'reason' => 'Score below minimum threshold (' . $min_score . ')',
            'score' => $score['score']
        ];
    }
    
    return [
        'eligible' => true,
        'score' => $score['score'],
        'grade' => $score['grade']
    ];
}

// Get assessment statistics
function cip_get_assessment_statistics($assessment_id) {
    global $wpdb;
    $table_assess = $wpdb->prefix . 'company_assessments';
    
    $assessment = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_assess WHERE id = %d",
        $assessment_id
    ), ARRAY_A);
    
    if (!$assessment) {
        return false;
    }
    
    $answers = json_decode($assessment['answers'], true);
    if (!$answers) {
        return false;
    }
    
    $stats = [
        'total_questions' => count($answers),
        'completed_questions' => 0,
        'empty_questions' => 0,
        'average_answer_length' => 0,
        'progress_percentage' => $assessment['progress'] * 20 // 5 steps = 100%
    ];
    
    $total_length = 0;
    foreach ($answers as $answer) {
        if (!empty(trim($answer))) {
            $stats['completed_questions']++;
            $total_length += strlen($answer);
        } else {
            $stats['empty_questions']++;
        }
    }
    
    if ($stats['completed_questions'] > 0) {
        $stats['average_answer_length'] = round($total_length / $stats['completed_questions']);
    }
    
    return $stats;
}

// Scoring criteria helper
function cip_get_scoring_criteria() {
    return [
        'environmental' => [
            'name' => 'Environmental Performance',
            'weight' => 0.35,
            'description' => 'Climate action, resource management, pollution control'
        ],
        'social' => [
            'name' => 'Social Responsibility',
            'weight' => 0.35,
            'description' => 'Labor practices, human rights, community engagement'
        ],
        'governance' => [
            'name' => 'Corporate Governance',
            'weight' => 0.30,
            'description' => 'Ethics, transparency, accountability, board structure'
        ]
    ];
}

// Calculate weighted score
function cip_calculate_weighted_score($answers) {
    $criteria = cip_get_scoring_criteria();
    $scores = [];
    
    // Categorize questions by criteria
    foreach ($answers as $question => $answer) {
        $category = cip_categorize_question($question);
        if (!isset($scores[$category])) {
            $scores[$category] = ['total' => 0, 'count' => 0];
        }
        
        $points = cip_default_score_answer($answer);
        $scores[$category]['total'] += $points;
        $scores[$category]['count']++;
    }
    
    // Calculate weighted average
    $weighted_score = 0;
    foreach ($criteria as $key => $criterion) {
        if (isset($scores[$key]) && $scores[$key]['count'] > 0) {
            $category_score = ($scores[$key]['total'] / ($scores[$key]['count'] * 10)) * 100;
            $weighted_score += $category_score * $criterion['weight'];
        }
    }
    
    return round($weighted_score, 2);
}

// Categorize question
function cip_categorize_question($question) {
    $question_lower = strtolower($question);
    
    $environmental_keywords = ['climate', 'carbon', 'emission', 'energy', 'waste', 'water', 'pollution', 'environment'];
    $social_keywords = ['employee', 'labor', 'safety', 'diversity', 'community', 'human rights', 'social'];
    $governance_keywords = ['governance', 'board', 'ethics', 'compliance', 'transparency', 'risk', 'policy'];
    
    foreach ($environmental_keywords as $keyword) {
        if (strpos($question_lower, $keyword) !== false) {
            return 'environmental';
        }
    }
    
    foreach ($social_keywords as $keyword) {
        if (strpos($question_lower, $keyword) !== false) {
            return 'social';
        }
    }
    
    foreach ($governance_keywords as $keyword) {
        if (strpos($question_lower, $keyword) !== false) {
            return 'governance';
        }
    }
    
    return 'governance'; // Default
}

// Get score recommendations
function cip_get_score_recommendations($score, $grade) {
    $recommendations = [];
    
    if ($score < 50) {
        $recommendations[] = 'Immediate action required to establish basic ESG practices';
        $recommendations[] = 'Consider hiring an ESG consultant';
        $recommendations[] = 'Focus on compliance with environmental regulations';
    } elseif ($score < 60) {
        $recommendations[] = 'Develop comprehensive ESG policies';
        $recommendations[] = 'Implement regular sustainability reporting';
        $recommendations[] = 'Engage stakeholders in ESG initiatives';
    } elseif ($score < 70) {
        $recommendations[] = 'Expand ESG program scope';
        $recommendations[] = 'Set measurable sustainability targets';
        $recommendations[] = 'Increase transparency in ESG reporting';
    } elseif ($score < 80) {
        $recommendations[] = 'Pursue industry-specific ESG certifications';
        $recommendations[] = 'Implement advanced environmental technologies';
        $recommendations[] = 'Enhance supply chain sustainability';
    } elseif ($score < 90) {
        $recommendations[] = 'Achieve carbon neutrality';
        $recommendations[] = 'Lead industry in ESG innovation';
        $recommendations[] = 'Publish comprehensive sustainability report';
    } else {
        $recommendations[] = 'Maintain excellence and share best practices';
        $recommendations[] = 'Mentor other organizations in ESG';
        $recommendations[] = 'Pursue international ESG recognition';
    }
    
    return $recommendations;
}

// Auto-score assessment on submission
add_action('cip_assessment_submitted', 'cip_auto_score_assessment', 10, 2);
function cip_auto_score_assessment($assessment_id, $user_id) {
    // Only auto-score if enabled in settings
    if (!get_option('cip_enable_auto_scoring', false)) {
        return;
    }
    
    $score = cip_calculate_assessment_score($assessment_id);
    if ($score === false) {
        return;
    }
    
    $grade = cip_get_grade_from_score($score);
    
    global $wpdb;
    $table_scores = $wpdb->prefix . 'cip_assessment_scores';
    
    // Insert auto-score
    $wpdb->insert(
        $table_scores,
        [
            'assessment_id' => $assessment_id,
            'user_id' => $user_id,
            'score' => $score,
            'grade' => $grade,
            'comments' => 'Automatically scored by system',
            'scored_by' => 0, // 0 = system
            'scored_at' => current_time('mysql')
        ]
    );
}