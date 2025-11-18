<?php
/**
 * ============================================
 * FILE 3: pdf-generator.php (NEW)
 * ============================================
 */

if (!defined('ABSPATH')) exit;

require_once CIP_PLUGIN_DIR . 'vendor/autoload.php'; // For TCPDF or similar

class CIP_PDF_Generator {
    
    /**
     * Generate Assessment PDF
     */
    public static function generate_assessment_pdf($user_id) {
        global $wpdb;
        
        $table_assessments = $wpdb->prefix . 'company_assessments';
        $table_registrations = $wpdb->prefix . 'company_registrations';
        
        $assessment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_assessments WHERE user_id = %d",
            $user_id
        ), ARRAY_A);
        
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_registrations WHERE id = %d",
            $user_id
        ), ARRAY_A);
        
        if (!$assessment || !$registration) {
            return false;
        }
        
        $assessment_data = json_decode($assessment['assessment_json'], true);
        
        // Initialize PDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('CleanIndex Portal');
        $pdf->SetAuthor($registration['company_name']);
        $pdf->SetTitle('ESG Assessment Submission');
        $pdf->SetSubject('CSRD/ESRS Assessment');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);
        
        // Add page
        $pdf->AddPage();
        
        // Header
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetTextColor(76, 175, 80);
        $pdf->Cell(0, 15, 'ESG Assessment Submission', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 7, $registration['company_name'], 0, 1, 'C');
        $pdf->Cell(0, 7, 'Submitted: ' . date('F j, Y', strtotime($assessment['submitted_at'])), 0, 1, 'C');
        
        $pdf->Ln(10);
        
        // Questions and Answers
        $questions = self::get_assessment_questions();
        
        foreach ($questions as $step => $step_data) {
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetFillColor(76, 175, 80);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 10, 'Step ' . $step . ': ' . $step_data['title'], 0, 1, 'L', true);
            $pdf->Ln(5);
            
            foreach ($step_data['questions'] as $q_id => $question) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->MultiCell(0, 6, $question, 0, 'L');
                
                $pdf->SetFont('helvetica', '', 10);
                $pdf->SetTextColor(60, 60, 60);
                $answer = isset($assessment_data[$q_id]) ? $assessment_data[$q_id] : 'Not answered';
                $pdf->SetFillColor(245, 245, 245);
                $pdf->MultiCell(0, 6, $answer, 0, 'L', true);
                $pdf->Ln(5);
            }
            
            $pdf->Ln(5);
        }
        
        // Save PDF
        $upload_dir = CIP_UPLOAD_DIR . 'assessments/' . $user_id . '/';
        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }
        
        $filename = 'assessment_' . $user_id . '_' . time() . '.pdf';
        $filepath = $upload_dir . $filename;
        
        $pdf->Output($filepath, 'F');
        
        return [
            'success' => true,
            'path' => $filepath,
            'url' => CIP_UPLOAD_URL . 'assessments/' . $user_id . '/' . $filename
        ];
    }
    
    /**
     * Generate Certificate PDF
     */
    public static function generate_certificate_pdf($user_id, $grade = 'ESG+') {
        global $wpdb;
        
        $table_registrations = $wpdb->prefix . 'company_registrations';
        
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_registrations WHERE id = %d",
            $user_id
        ), ARRAY_A);
        
        if (!$registration) {
            return false;
        }
        
        // Initialize PDF with landscape orientation
        $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
        
        $pdf->SetCreator('CleanIndex Portal');
        $pdf->SetTitle('ESG Certificate');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(FALSE);
        
        $pdf->AddPage();
        
        // Background gradient
        $pdf->SetFillColor(250, 250, 250);
        $pdf->Rect(0, 0, 297, 210, 'F');
        
        // Border
        $pdf->SetLineWidth(3);
        $pdf->SetDrawColor(76, 175, 80);
        $pdf->Rect(10, 10, 277, 190, 'D');
        
        // Logo/Icon
        $pdf->SetFont('helvetica', 'B', 48);
        $pdf->SetTextColor(76, 175, 80);
        $pdf->SetXY(0, 30);
        $pdf->Cell(0, 20, '🌱', 0, 1, 'C');
        
        // Title
        $pdf->SetFont('helvetica', 'B', 32);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(0, 60);
        $pdf->Cell(0, 15, 'ESG CERTIFICATE', 0, 1, 'C');
        
        // Grade
        $pdf->SetFont('helvetica', 'B', 48);
        $pdf->SetTextColor(76, 175, 80);
        $pdf->SetXY(0, 85);
        $pdf->Cell(0, 20, $grade, 0, 1, 'C');
        
        // Company name
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(0, 115);
        $pdf->Cell(0, 10, strtoupper($registration['company_name']), 0, 1, 'C');
        
        // Description
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY(40, 135);
        $pdf->MultiCell(217, 6, 'This certificate confirms that the above organization has successfully completed the CSRD/ESRS compliant ESG assessment and meets the standards for ' . $grade . ' certification.', 0, 'C');
        
        // Date and Certificate Number
        $cert_number = 'CI-' . strtoupper(substr(md5($user_id . time()), 0, 10));
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetXY(0, 165);
        $pdf->Cell(0, 5, 'Certificate Number: ' . $cert_number, 0, 1, 'C');
        $pdf->Cell(0, 5, 'Issue Date: ' . date('F j, Y'), 0, 1, 'C');
        $pdf->Cell(0, 5, 'Valid Until: ' . date('F j, Y', strtotime('+1 year')), 0, 1, 'C');
        
        // Save PDF
        $upload_dir = CIP_UPLOAD_DIR . 'certificates/';
        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }
        
        $filename = 'certificate_' . $user_id . '_' . time() . '.pdf';
        $filepath = $upload_dir . $filename;
        
        $pdf->Output($filepath, 'F');
        
        // Store certificate info
        update_user_meta(get_current_user_id(), 'cip_certificate_generated', true);
        update_user_meta(get_current_user_id(), 'cip_certificate_url', CIP_UPLOAD_URL . 'certificates/' . $filename);
        update_user_meta(get_current_user_id(), 'cip_certificate_grade', $grade);
        update_user_meta(get_current_user_id(), 'cip_certificate_number', $cert_number);
        
        return [
            'success' => true,
            'path' => $filepath,
            'url' => CIP_UPLOAD_URL . 'certificates/' . $filename,
            'cert_number' => $cert_number,
            'grade' => $grade
        ];
    }
    
    /**
     * Get assessment questions structure
     */
    private static function get_assessment_questions() {
        return [
            1 => [
                'title' => 'General Requirements & Materiality Analysis',
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
            ],
            3 => [
                'title' => 'Strategy & Risk Management',
                'questions' => [
                    'q3_1' => 'How do material IROs influence your strategy?',
                    'q3_2' => 'What are your strategic adjustments to sustainability challenges?',
                    'q3_3' => 'How do you identify, assess, and prioritize material IROs?',
                    'q3_4' => 'What are your policies and mitigation strategies?'
                ]
            ],
            4 => [
                'title' => 'Environment (E1-E5)',
                'questions' => [
                    'q4_1_scope1' => 'Scope 1 emissions',
                    'q4_1_scope2' => 'Scope 2 emissions',
                    'q4_1_scope3' => 'Scope 3 emissions',
                    'q4_2' => 'How are you reducing emissions?',
                    'q4_3' => 'What emissions do you cause and how do you minimize them?',
                    'q4_4_usage' => 'Water usage',
                    'q4_4_management' => 'Water management strategies',
                    'q4_5' => 'Impact on ecosystems and biodiversity protection',
                    'q4_6_waste' => 'Total waste produced',
                    'q4_6_recycled' => 'Percentage of recycled materials'
                ]
            ],
            5 => [
                'title' => 'Social & Metrics',
                'questions' => [
                    'q5_1_employees' => 'Total employees',
                    'q5_1_diversity' => 'Diversity statistics',
                    'q5_1_prevention' => 'Anti-discrimination measures',
                    'q5_2' => 'Labor conditions at suppliers',
                    'q5_3' => 'Impact on local communities',
                    'q5_4' => 'Customer health and privacy protection',
                    'q5_5' => 'KPIs and targets',
                    'q5_6' => 'Sustainability report audit'
                ]
            ]
        ];
    }
    
    /**
     * Calculate grade based on assessment score
     */
    public static function calculate_grade($assessment_data) {
        $total_score = 0;
        $max_score = 0;
        
        foreach ($assessment_data as $key => $value) {
            if (strpos($key, 'q') === 0 && !empty($value)) {
                $total_score++;
            }
            if (strpos($key, 'q') === 0) {
                $max_score++;
            }
        }
        
        $percentage = ($total_score / $max_score) * 100;
        
        if ($percentage >= 95) return 'ESG+++';
        if ($percentage >= 85) return 'ESG++';
        if ($percentage >= 75) return 'ESG+';
        return 'ESG';
    }
}