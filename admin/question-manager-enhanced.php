<?php

/**
 * ============================================
 * SOLUTION 2: QUESTION MANAGEMENT SYSTEM
 * ============================================
 * 
 * ADD THIS TO: admin/questions-manager-enhanced.php (ENHANCED)
 */

function cip_get_default_questions() {
    return [
        1 => [
            'title' => 'General Requirements & Materiality Analysis (ESRS 1 & 2)',
            'questions' => [
                'q1_1' => 'What sustainability impacts, risks, and opportunities (IROs) does your company have?',
                'q1_2' => 'How have you engaged stakeholders in identifying material topics?',
                'q1_3' => 'What are the boundaries of your reporting?',
                'q1_4' => 'Have you conducted a step-by-step materiality analysis?',
                'q1_5' => 'Which ESRS standards are material for you?'
            ]
        ],
        2 => [
            'title' => 'Company Profile & Governance',
            'questions' => [
                'q2_1' => 'What is your business model?',
                'q2_2' => 'How do sustainability factors integrate into your business model?',
                'q2_3' => 'How is your board involved in managing sustainability IROs?',
                'q2_4' => 'What due diligence processes do you have?',
                'q2_5' => 'Which compensation structures are linked to sustainability?'
            ]
        ]
    ];
}

// Save custom questions
add_action('wp_ajax_cip_save_questions', 'cip_ajax_save_questions');

function cip_ajax_save_questions() {
    check_ajax_referer('cip_admin_action', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    $step = intval($_POST['step']);
    $action_type = sanitize_text_field($_POST['action_type']); // add, edit, delete
    $question_id = sanitize_text_field($_POST['question_id']);
    $question_text = sanitize_textarea_field($_POST['question_text']);
    $question_type = sanitize_text_field($_POST['question_type']);
    $required = !empty($_POST['required']);
    
    $questions = get_option('cip_assessment_questions', cip_get_default_questions());
    
    if (!isset($questions[$step])) {
        $questions[$step] = ['title' => 'Step ' . $step, 'questions' => []];
    }
    
    if ($action_type === 'add') {
        // Generate unique question ID
        $next_id = count($questions[$step]['questions']) + 1;
        $new_q_id = 'q' . $step . '_' . $next_id;
        
        $questions[$step]['questions'][$new_q_id] = [
            'text' => $question_text,
            'type' => $question_type,
            'required' => $required
        ];
    } elseif ($action_type === 'edit') {
        if (isset($questions[$step]['questions'][$question_id])) {
            $questions[$step]['questions'][$question_id]['text'] = $question_text;
            $questions[$step]['questions'][$question_id]['type'] = $question_type;
            $questions[$step]['questions'][$question_id]['required'] = $required;
        }
    } elseif ($action_type === 'delete') {
        unset($questions[$step]['questions'][$question_id]);
    }
    
    update_option('cip_assessment_questions', $questions);
    
    wp_send_json_success(['message' => 'Question updated successfully']);
}