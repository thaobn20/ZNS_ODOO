<?php
/**
 * Utilities Helper Class
 * File: includes/class-utilities.php
 * 
 * Common utility functions and helpers
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vefify_Quiz_Utilities {
    
    /**
     * Format phone number to Vietnamese standard
     */
    public static function format_phone_number($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);
        
        // Convert to standard format
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            return $phone; // Already in correct format
        } elseif (strlen($phone) === 9) {
            return '0' . $phone; // Add leading zero
        } elseif (strlen($phone) === 12 && substr($phone, 0, 2) === '84') {
            return '0' . substr($phone, 2); // Convert from +84 format
        }
        
        return $phone; // Return as-is if format unclear
    }
    
    /**
     * Validate Vietnamese phone number
     */
    public static function validate_phone_number($phone) {
        $phone = self::format_phone_number($phone);
        
        // Vietnamese mobile number patterns
        $patterns = array(
            '/^0(3[2-9]|5[689]|7[06-9]|8[1-689]|9[0-46-9])[0-9]{7}$/', // Mobile
            '/^0(2[0-9])[0-9]{8}$/' // Landline
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate unique session ID
     */
    public static function generate_session_id($prefix = 'vq') {
        return $prefix . '_' . uniqid() . '_' . wp_generate_password(8, false);
    }
    
    /**
     * Generate secure gift code
     */
    public static function generate_gift_code($prefix = 'GIFT', $length = 8) {
        $code = $prefix . strtoupper(wp_generate_password($length, false, false));
        
        // Ensure uniqueness by checking database
        global $wpdb;
        $table_name = $wpdb->prefix . VEFIFY_QUIZ_TABLE_PREFIX . 'participants';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE gift_code = %s",
            $code
        ));
        
        if ($exists) {
            // Recursive call if code exists
            return self::generate_gift_code($prefix, $length);
        }
        
        return $code;
    }
    
    /**
     * Calculate quiz score
     */
    public static function calculate_score($answers, $questions) {
        $score = 0;
        $total_points = 0;
        
        foreach ($questions as $question) {
            $question_id = $question->id;
            $user_answers = isset($answers[$question_id]) ? (array)$answers[$question_id] : array();
            
            // Get correct answers
            $correct_answers = array();
            foreach ($question->options as $option) {
                if ($option->is_correct) {
                    $correct_answers[] = $option->id;
                }
            }
            
            // Check if answer is correct
            $user_answers = array_map('intval', $user_answers);
            sort($user_answers);
            sort($correct_answers);
            
            $is_correct = ($user_answers === $correct_answers);
            
            if ($is_correct) {
                $score += $question->points;
            }
            
            $total_points += $question->points;
        }
        
        return array(
            'score' => $score,
            'total_points' => $total_points,
            'percentage' => $total_points > 0 ? round(($score / $total_points) * 100, 2) : 0
        );
    }
    
    /**
     * Get Vietnam provinces list
     */
    public static function get_vietnam_provinces() {
        return array(
            'hanoi' => 'Hanoi',
            'hcm' => 'Ho Chi Minh City',
            'danang' => 'Da Nang',
            'haiphong' => 'Hai Phong',
            'cantho' => 'Can Tho',
            'angiang' => 'An Giang',
            'bariavungtau' => 'Ba Ria - Vung Tau',
            'bacgiang' => 'Bac Giang',
            'backan' => 'Bac Kan',
            'baclieu' => 'Bac Lieu',
            'bacninh' => 'Bac Ninh',
            'bentre' => 'Ben Tre',
            'binhdinh' => 'Binh Dinh',
            'binhduong' => 'Binh Duong',
            'binhphuoc' => 'Binh Phuoc',
            'binhthuan' => 'Binh Thuan',
            'camau' => 'Ca Mau',
            'caobang' => 'Cao Bang',
            'daklak' => 'Dak Lak',
            'daknong' => 'Dak Nong',
            'dienbien' => 'Dien Bien',
            'dongnai' => 'Dong Nai',
            'dongthap' => 'Dong Thap',
            'gialai' => 'Gia Lai',
            'hagiang' => 'Ha Giang',
            'hanam' => 'Ha Nam',
            'hatinh' => 'Ha Tinh',
            'haiduong' => 'Hai Duong',
            'haugiang' => 'Hau Giang',
            'hoabinh' => 'Hoa Binh',
            'hungyen' => 'Hung Yen',
            'khanhhoa' => 'Khanh Hoa',
            'kiengiang' => 'Kien Giang',
            'kontum' => 'Kon Tum',
            'laicau' => 'Lai Chau',
            'lamdong' => 'Lam Dong',
            'langson' => 'Lang Son',
            'laocai' => 'Lao Cai',
            'longan' => 'Long An',
            'namdinh' => 'Nam Dinh',
            'nghean' => 'Nghe An',
            'ninhbinh' => 'Ninh Binh',
            'ninhthuan' => 'Ninh Thuan',
            'phutho' => 'Phu Tho',
            'phuyen' => 'Phu Yen',
            'quangbinh' => 'Quang Binh',
            'quangnam' => 'Quang Nam',
            'quangngai' => 'Quang Ngai',
            'quangninh' => 'Quang Ninh',
            'quangtri' => 'Quang Tri',
            'soctrang' => 'Soc Trang',
            'sonla' => 'Son La',
            'tayninh' => 'Tay Ninh',
            'thaibinh' => 'Thai Binh',
            'thainguyen' => 'Thai Nguyen',
            'thanhhoa' => 'Thanh Hoa',
            'thuathienhue' => 'Thua Thien Hue',
            'tiengiang' => 'Tien Giang',
            'travinh' => 'Tra Vinh',
            'tuyenquang' => 'Tuyen Quang',
            'vinhlong' => 'Vinh Long',
            'vinhphuc' => 'Vinh Phuc',
            'yenbai' => 'Yen Bai'
        );
    }
    
    /**
     * Sanitize and validate quiz data
     */
    public static function sanitize_quiz_data($data) {
        $sanitized = array();
        
        if (isset($data['full_name'])) {
            $sanitized['full_name'] = sanitize_text_field($data['full_name']);
        }
        
        if (isset($data['phone_number'])) {
            $sanitized['phone_number'] = self::format_phone_number($data['phone_number']);
        }
        
        if (isset($data['province'])) {
            $provinces = array_keys(self::get_vietnam_provinces());
            $sanitized['province'] = in_array($data['province'], $provinces) ? $data['province'] : '';
        }
        
        if (isset($data['pharmacy_code'])) {
            $sanitized['pharmacy_code'] = sanitize_text_field($data['pharmacy_code']);
        }
        
        if (isset($data['email'])) {
            $sanitized['email'] = sanitize_email($data['email']);
        }
        
        return $sanitized;
    }
    
    /**
     * Convert seconds to human readable time
     */
    public static function seconds_to_time($seconds) {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $seconds = $seconds % 60;
            return $minutes . 'm ' . $seconds . 's';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $minutes . 'm';
        }
    }
    
    /**
     * Format number with Vietnamese locale
     */
    public static function format_number($number, $decimals = 0) {
        return number_format($number, $decimals, ',', '.');
    }
    
    /**
     * Generate CSV export
     */
    public static function generate_csv($data, $headers = array()) {
        if (empty($data)) {
            return false;
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Add headers
        if (!empty($headers)) {
            fputcsv($output, $headers);
        } elseif (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
        }
        
        // Add data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return $csv_content;
    }
    
    /**
     * Log activity for debugging
     */
    public static function log_activity($message, $data = array(), $level = 'info') {
        if (!WP_DEBUG_LOG) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'data' => $data,
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );
        
        error_log('Vefify Quiz [' . strtoupper($level) . ']: ' . json_encode($log_entry));
    }
    
    /**
     * Check if user is mobile
     */
    public static function is_mobile() {
        return wp_is_mobile();
    }
    
    /**
     * Get client IP address
     */
    public static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Validate email address
     */
    public static function validate_email($email) {
        return is_email($email);
    }
    
    /**
     * Rate limiting check
     */
    public static function check_rate_limit($key, $limit = 10, $window = 60) {
        $transient_key = 'vefify_rate_limit_' . md5($key);
        $current_count = get_transient($transient_key);
        
        if ($current_count === false) {
            set_transient($transient_key, 1, $window);
            return true;
        }
        
        if ($current_count >= $limit) {
            return false;
        }
        
        set_transient($transient_key, $current_count + 1, $window);
        return true;
    }
    
    /**
     * Send notification email
     */
    public static function send_notification($to, $subject, $message, $type = 'plain') {
        $headers = array();
        
        if ($type === 'html') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }
        
        $headers[] = 'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>';
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Get system information
     */
    public static function get_system_info() {
        global $wpdb;
        
        return array(
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'mysql_version' => $wpdb->get_var('SELECT VERSION()'),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        );
    }
}