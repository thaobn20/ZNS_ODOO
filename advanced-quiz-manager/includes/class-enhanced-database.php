<?php
/**
 * Enhanced Database Setup for Advanced Quiz Manager
 * File: includes/class-enhanced-database.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class AQM_Enhanced_Database {
    
    public function __construct() {
        add_action('wp_loaded', array($this, 'check_and_create_missing_tables'));
        add_action('aqm_enhanced_activation', array($this, 'create_all_tables'));
        register_activation_hook(AQM_PLUGIN_PATH . 'advanced-quiz-manager.php', array($this, 'on_activation'));
    }
    
    public function on_activation() {
        $this->create_all_tables();
        $this->populate_sample_data();
        
        // Redirect to success page
        set_transient('aqm_installation_redirect', true, 30);
    }
    
    public function check_and_create_missing_tables() {
        global $wpdb;
        
        $missing_tables = $this->get_missing_tables();
        
        if (!empty($missing_tables)) {
            foreach ($missing_tables as $table) {
                $this->create_table($table);
            }
            
            // Populate with initial data if tables were missing
            $this->populate_initial_data();
        }
    }
    
    private function get_missing_tables() {
        global $wpdb;
        $missing = array();
        
        $required_tables = array(
            'aqm_provinces',
            'aqm_districts', 
            'aqm_wards',
            'aqm_gifts',
            'aqm_gift_awards'
        );
        
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $missing[] = $table;
            }
        }
        
        return $missing;
    }
    
    private function create_table($table_name) {
        switch ($table_name) {
            case 'aqm_provinces':
                $this->create_provinces_table();
                break;
            case 'aqm_districts':
                $this->create_districts_table();
                break;
            case 'aqm_wards':
                $this->create_wards_table();
                break;
            case 'aqm_gifts':
                $this->create_gifts_table();
                break;
            case 'aqm_gift_awards':
                $this->create_gift_awards_table();
                break;
        }
    }
    
    public function create_all_tables() {
        $this->create_provinces_table();
        $this->create_districts_table();
        $this->create_wards_table();
        $this->create_gifts_table();
        $this->create_gift_awards_table();
        $this->enhance_existing_tables();
    }
    
    private function create_provinces_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'aqm_provinces';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL,
            name varchar(100) NOT NULL,
            name_en varchar(100),
            full_name varchar(150),
            full_name_en varchar(150),
            code_name varchar(50),
            administrative_unit_id int(11),
            administrative_region_id int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY name (name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_districts_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'aqm_districts';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL,
            name varchar(100) NOT NULL,
            name_en varchar(100),
            full_name varchar(150),
            full_name_en varchar(150),
            code_name varchar(50),
            province_code varchar(10) NOT NULL,
            administrative_unit_id int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY province_code (province_code),
            KEY name (name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_wards_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'aqm_wards';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            code varchar(10) NOT NULL,
            name varchar(100) NOT NULL,
            name_en varchar(100),
            full_name varchar(150),
            full_name_en varchar(150),
            code_name varchar(50),
            district_code varchar(10) NOT NULL,
            administrative_unit_id int(11),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY district_code (district_code),
            KEY name (name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_gifts_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'aqm_gifts';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            campaign_id int(11) NOT NULL,
            gift_name varchar(255) NOT NULL,
            gift_type enum('voucher','discount','physical','points','custom') DEFAULT 'voucher',
            gift_value varchar(255),
            description text,
            quantity_total int(11) DEFAULT 0,
            quantity_remaining int(11) DEFAULT 0,
            min_score int(11) DEFAULT 0,
            max_score int(11) DEFAULT 100,
            probability decimal(5,2) DEFAULT 10.00,
            is_active tinyint(1) DEFAULT 1,
            valid_from datetime,
            valid_until datetime,
            gift_image varchar(255),
            gift_code_prefix varchar(20),
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY is_active (is_active),
            KEY min_score (min_score),
            KEY probability (probability)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_gift_awards_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'aqm_gift_awards';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            response_id int(11) NOT NULL,
            gift_id int(11) NOT NULL,
            campaign_id int(11) NOT NULL,
            participant_email varchar(255),
            participant_name varchar(255),
            gift_code varchar(100),
            score_achieved int(11),
            awarded_at datetime DEFAULT CURRENT_TIMESTAMP,
            claimed_at datetime NULL,
            claim_status enum('awarded','claimed','expired','revoked') DEFAULT 'awarded',
            claim_ip varchar(45),
            claim_details longtext,
            expiry_date datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY gift_code (gift_code),
            KEY response_id (response_id),
            KEY gift_id (gift_id),
            KEY campaign_id (campaign_id),
            KEY participant_email (participant_email),
            KEY claim_status (claim_status),
            KEY awarded_at (awarded_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function enhance_existing_tables() {
        global $wpdb;
        
        // Enhance questions table
        $questions_table = $wpdb->prefix . 'aqm_questions';
        
        $existing_columns = $wpdb->get_col("DESCRIBE $questions_table", 0);
        
        if (!in_array('gift_eligibility', $existing_columns)) {
            $wpdb->query("ALTER TABLE $questions_table ADD COLUMN gift_eligibility tinyint(1) DEFAULT 0");
        }
        
        if (!in_array('scoring_weight', $existing_columns)) {
            $wpdb->query("ALTER TABLE $questions_table ADD COLUMN scoring_weight decimal(3,2) DEFAULT 1.00");
        }
        
        if (!in_array('question_group', $existing_columns)) {
            $wpdb->query("ALTER TABLE $questions_table ADD COLUMN question_group varchar(100)");
        }
        
        // Enhance responses table
        $responses_table = $wpdb->prefix . 'aqm_responses';
        
        $existing_columns = $wpdb->get_col("DESCRIBE $responses_table", 0);
        
        if (!in_array('final_score', $existing_columns)) {
            $wpdb->query("ALTER TABLE $responses_table ADD COLUMN final_score int(11) DEFAULT 0");
        }
        
        if (!in_array('gift_eligible', $existing_columns)) {
            $wpdb->query("ALTER TABLE $responses_table ADD COLUMN gift_eligible tinyint(1) DEFAULT 0");
        }
        
        if (!in_array('province_selected', $existing_columns)) {
            $wpdb->query("ALTER TABLE $responses_table ADD COLUMN province_selected varchar(100)");
        }
        
        if (!in_array('district_selected', $existing_columns)) {
            $wpdb->query("ALTER TABLE $responses_table ADD COLUMN district_selected varchar(100)");
        }
        
        if (!in_array('ward_selected', $existing_columns)) {
            $wpdb->query("ALTER TABLE $responses_table ADD COLUMN ward_selected varchar(100)");
        }
    }
    
    private function populate_initial_data() {
        $this->populate_vietnam_provinces();
        $this->populate_vietnam_districts(); 
        $this->populate_vietnam_wards();
    }
    
    private function populate_sample_data() {
        $this->populate_initial_data();
        $this->create_sample_campaign_with_gifts();
    }
    
    private function populate_vietnam_provinces() {
        global $wpdb;
        
        // Check if already populated
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_provinces");
        if ($count > 0) return;
        
        $provinces = array(
            array('01', 'Hà Nội', 'Ha Noi', 'Thành phố Hà Nội', 'Ha Noi City', 'ha-noi'),
            array('02', 'Hà Giang', 'Ha Giang', 'Tỉnh Hà Giang', 'Ha Giang Province', 'ha-giang'),
            array('04', 'Cao Bằng', 'Cao Bang', 'Tỉnh Cao Bằng', 'Cao Bang Province', 'cao-bang'),
            array('06', 'Bắc Kạn', 'Bac Kan', 'Tỉnh Bắc Kạn', 'Bac Kan Province', 'bac-kan'),
            array('08', 'Tuyên Quang', 'Tuyen Quang', 'Tỉnh Tuyên Quang', 'Tuyen Quang Province', 'tuyen-quang'),
            array('10', 'Lào Cai', 'Lao Cai', 'Tỉnh Lào Cai', 'Lao Cai Province', 'lao-cai'),
            array('11', 'Điện Biên', 'Dien Bien', 'Tỉnh Điện Biên', 'Dien Bien Province', 'dien-bien'),
            array('12', 'Lai Châu', 'Lai Chau', 'Tỉnh Lai Châu', 'Lai Chau Province', 'lai-chau'),
            array('14', 'Sơn La', 'Son La', 'Tỉnh Sơn La', 'Son La Province', 'son-la'),
            array('15', 'Yên Bái', 'Yen Bai', 'Tỉnh Yên Bái', 'Yen Bai Province', 'yen-bai'),
            array('17', 'Hoà Bình', 'Hoa Binh', 'Tỉnh Hoà Bình', 'Hoa Binh Province', 'hoa-binh'),
            array('19', 'Thái Nguyên', 'Thai Nguyen', 'Tỉnh Thái Nguyên', 'Thai Nguyen Province', 'thai-nguyen'),
            array('20', 'Lạng Sơn', 'Lang Son', 'Tỉnh Lạng Sơn', 'Lang Son Province', 'lang-son'),
            array('22', 'Quảng Ninh', 'Quang Ninh', 'Tỉnh Quảng Ninh', 'Quang Ninh Province', 'quang-ninh'),
            array('24', 'Bắc Giang', 'Bac Giang', 'Tỉnh Bắc Giang', 'Bac Giang Province', 'bac-giang'),
            array('25', 'Phú Thọ', 'Phu Tho', 'Tỉnh Phú Thọ', 'Phu Tho Province', 'phu-tho'),
            array('26', 'Vĩnh Phúc', 'Vinh Phuc', 'Tỉnh Vĩnh Phúc', 'Vinh Phuc Province', 'vinh-phuc'),
            array('27', 'Bắc Ninh', 'Bac Ninh', 'Tỉnh Bắc Ninh', 'Bac Ninh Province', 'bac-ninh'),
            array('30', 'Hải Dương', 'Hai Duong', 'Tỉnh Hải Dương', 'Hai Duong Province', 'hai-duong'),
            array('31', 'Hải Phòng', 'Hai Phong', 'Thành phố Hải Phòng', 'Hai Phong City', 'hai-phong'),
            array('33', 'Hưng Yên', 'Hung Yen', 'Tỉnh Hưng Yên', 'Hung Yen Province', 'hung-yen'),
            array('34', 'Thái Bình', 'Thai Binh', 'Tỉnh Thái Bình', 'Thai Binh Province', 'thai-binh'),
            array('35', 'Hà Nam', 'Ha Nam', 'Tỉnh Hà Nam', 'Ha Nam Province', 'ha-nam'),
            array('36', 'Nam Định', 'Nam Dinh', 'Tỉnh Nam Định', 'Nam Dinh Province', 'nam-dinh'),
            array('37', 'Ninh Bình', 'Ninh Binh', 'Tỉnh Ninh Bình', 'Ninh Binh Province', 'ninh-binh'),
            array('38', 'Thanh Hóa', 'Thanh Hoa', 'Tỉnh Thanh Hóa', 'Thanh Hoa Province', 'thanh-hoa'),
            array('40', 'Nghệ An', 'Nghe An', 'Tỉnh Nghệ An', 'Nghe An Province', 'nghe-an'),
            array('42', 'Hà Tĩnh', 'Ha Tinh', 'Tỉnh Hà Tĩnh', 'Ha Tinh Province', 'ha-tinh'),
            array('44', 'Quảng Bình', 'Quang Binh', 'Tỉnh Quảng Bình', 'Quang Binh Province', 'quang-binh'),
            array('45', 'Quảng Trị', 'Quang Tri', 'Tỉnh Quảng Trị', 'Quang Tri Province', 'quang-tri'),
            array('46', 'Thừa Thiên Huế', 'Thua Thien Hue', 'Tỉnh Thừa Thiên Huế', 'Thua Thien Hue Province', 'thua-thien-hue'),
            array('48', 'Đà Nẵng', 'Da Nang', 'Thành phố Đà Nẵng', 'Da Nang City', 'da-nang'),
            array('49', 'Quảng Nam', 'Quang Nam', 'Tỉnh Quảng Nam', 'Quang Nam Province', 'quang-nam'),
            array('51', 'Quảng Ngãi', 'Quang Ngai', 'Tỉnh Quảng Ngãi', 'Quang Ngai Province', 'quang-ngai'),
            array('52', 'Bình Định', 'Binh Dinh', 'Tỉnh Bình Định', 'Binh Dinh Province', 'binh-dinh'),
            array('54', 'Phú Yên', 'Phu Yen', 'Tỉnh Phú Yên', 'Phu Yen Province', 'phu-yen'),
            array('56', 'Khánh Hòa', 'Khanh Hoa', 'Tỉnh Khánh Hòa', 'Khanh Hoa Province', 'khanh-hoa'),
            array('58', 'Ninh Thuận', 'Ninh Thuan', 'Tỉnh Ninh Thuận', 'Ninh Thuan Province', 'ninh-thuan'),
            array('60', 'Bình Thuận', 'Binh Thuan', 'Tỉnh Bình Thuận', 'Binh Thuan Province', 'binh-thuan'),
            array('62', 'Kon Tum', 'Kon Tum', 'Tỉnh Kon Tum', 'Kon Tum Province', 'kon-tum'),
            array('64', 'Gia Lai', 'Gia Lai', 'Tỉnh Gia Lai', 'Gia Lai Province', 'gia-lai'),
            array('66', 'Đắk Lắk', 'Dak Lak', 'Tỉnh Đắk Lắk', 'Dak Lak Province', 'dak-lak'),
            array('67', 'Đắk Nông', 'Dak Nong', 'Tỉnh Đắk Nông', 'Dak Nong Province', 'dak-nong'),
            array('68', 'Lâm Đồng', 'Lam Dong', 'Tỉnh Lâm Đồng', 'Lam Dong Province', 'lam-dong'),
            array('70', 'Bình Phước', 'Binh Phuoc', 'Tỉnh Bình Phước', 'Binh Phuoc Province', 'binh-phuoc'),
            array('72', 'Tây Ninh', 'Tay Ninh', 'Tỉnh Tây Ninh', 'Tay Ninh Province', 'tay-ninh'),
            array('74', 'Bình Dương', 'Binh Duong', 'Tỉnh Bình Dương', 'Binh Duong Province', 'binh-duong'),
            array('75', 'Đồng Nai', 'Dong Nai', 'Tỉnh Đồng Nai', 'Dong Nai Province', 'dong-nai'),
            array('77', 'Bà Rịa - Vũng Tàu', 'Ba Ria - Vung Tau', 'Tỉnh Bà Rịa - Vũng Tàu', 'Ba Ria - Vung Tau Province', 'ba-ria-vung-tau'),
            array('79', 'Hồ Chí Minh', 'Ho Chi Minh', 'Thành phố Hồ Chí Minh', 'Ho Chi Minh City', 'ho-chi-minh'),
            array('80', 'Long An', 'Long An', 'Tỉnh Long An', 'Long An Province', 'long-an'),
            array('82', 'Tiền Giang', 'Tien Giang', 'Tỉnh Tiền Giang', 'Tien Giang Province', 'tien-giang'),
            array('83', 'Bến Tre', 'Ben Tre', 'Tỉnh Bến Tre', 'Ben Tre Province', 'ben-tre'),
            array('84', 'Trà Vinh', 'Tra Vinh', 'Tỉnh Trà Vinh', 'Tra Vinh Province', 'tra-vinh'),
            array('86', 'Vĩnh Long', 'Vinh Long', 'Tỉnh Vĩnh Long', 'Vinh Long Province', 'vinh-long'),
            array('87', 'Đồng Tháp', 'Dong Thap', 'Tỉnh Đồng Tháp', 'Dong Thap Province', 'dong-thap'),
            array('89', 'An Giang', 'An Giang', 'Tỉnh An Giang', 'An Giang Province', 'an-giang'),
            array('91', 'Kiên Giang', 'Kien Giang', 'Tỉnh Kiên Giang', 'Kien Giang Province', 'kien-giang'),
            array('92', 'Cần Thơ', 'Can Tho', 'Thành phố Cần Thơ', 'Can Tho City', 'can-tho'),
            array('93', 'Hậu Giang', 'Hau Giang', 'Tỉnh Hậu Giang', 'Hau Giang Province', 'hau-giang'),
            array('94', 'Sóc Trăng', 'Soc Trang', 'Tỉnh Sóc Trăng', 'Soc Trang Province', 'soc-trang'),
            array('95', 'Bạc Liêu', 'Bac Lieu', 'Tỉnh Bạc Liêu', 'Bac Lieu Province', 'bac-lieu'),
            array('96', 'Cà Mau', 'Ca Mau', 'Tỉnh Cà Mau', 'Ca Mau Province', 'ca-mau')
        );
        
        $table_name = $wpdb->prefix . 'aqm_provinces';
        
        foreach ($provinces as $province) {
            $wpdb->replace($table_name, array(
                'code' => $province[0],
                'name' => $province[1],
                'name_en' => $province[2],
                'full_name' => $province[3],
                'full_name_en' => $province[4],
                'code_name' => $province[5]
            ));
        }
    }
    
    private function populate_vietnam_districts() {
        global $wpdb;
        
        // Check if already populated
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_districts");
        if ($count > 0) return;
        
        $table_name = $wpdb->prefix . 'aqm_districts';
        
        // Sample districts for major cities
        $districts = array(
            // Ho Chi Minh City districts
            array('760', 'Quận 1', 'District 1', 'Quận 1', 'District 1', 'quan-1', '79'),
            array('761', 'Quận 2', 'District 2', 'Quận 2', 'District 2', 'quan-2', '79'),
            array('762', 'Quận 3', 'District 3', 'Quận 3', 'District 3', 'quan-3', '79'),
            array('763', 'Quận 4', 'District 4', 'Quận 4', 'District 4', 'quan-4', '79'),
            array('764', 'Quận 5', 'District 5', 'Quận 5', 'District 5', 'quan-5', '79'),
            array('765', 'Quận 6', 'District 6', 'Quận 6', 'District 6', 'quan-6', '79'),
            array('766', 'Quận 7', 'District 7', 'Quận 7', 'District 7', 'quan-7', '79'),
            array('767', 'Quận 8', 'District 8', 'Quận 8', 'District 8', 'quan-8', '79'),
            array('768', 'Quận 9', 'District 9', 'Quận 9', 'District 9', 'quan-9', '79'),
            array('769', 'Quận 10', 'District 10', 'Quận 10', 'District 10', 'quan-10', '79'),
            array('770', 'Quận 11', 'District 11', 'Quận 11', 'District 11', 'quan-11', '79'),
            array('771', 'Quận 12', 'District 12', 'Quận 12', 'District 12', 'quan-12', '79'),
            array('772', 'Quận Thủ Đức', 'Thu Duc District', 'Quận Thủ Đức', 'Thu Duc District', 'quan-thu-duc', '79'),
            array('773', 'Quận Gò Vấp', 'Go Vap District', 'Quận Gò Vấp', 'Go Vap District', 'quan-go-vap', '79'),
            array('774', 'Quận Bình Thạnh', 'Binh Thanh District', 'Quận Bình Thạnh', 'Binh Thanh District', 'quan-binh-thanh', '79'),
            array('775', 'Quận Tân Bình', 'Tan Binh District', 'Quận Tân Bình', 'Tan Binh District', 'quan-tan-binh', '79'),
            array('776', 'Quận Tân Phú', 'Tan Phu District', 'Quận Tân Phú', 'Tan Phu District', 'quan-tan-phu', '79'),
            array('777', 'Quận Phú Nhuận', 'Phu Nhuan District', 'Quận Phú Nhuận', 'Phu Nhuan District', 'quan-phu-nhuan', '79'),
            
            // Hanoi districts
            array('001', 'Quận Ba Đình', 'Ba Dinh District', 'Quận Ba Đình', 'Ba Dinh District', 'quan-ba-dinh', '01'),
            array('002', 'Quận Hoàn Kiếm', 'Hoan Kiem District', 'Quận Hoàn Kiếm', 'Hoan Kiem District', 'quan-hoan-kiem', '01'),
            array('003', 'Quận Tây Hồ', 'Tay Ho District', 'Quận Tây Hồ', 'Tay Ho District', 'quan-tay-ho', '01'),
            array('004', 'Quận Long Biên', 'Long Bien District', 'Quận Long Biên', 'Long Bien District', 'quan-long-bien', '01'),
            array('005', 'Quận Cầu Giấy', 'Cau Giay District', 'Quận Cầu Giấy', 'Cau Giay District', 'quan-cau-giay', '01'),
            array('006', 'Quận Đống Đa', 'Dong Da District', 'Quận Đống Đa', 'Dong Da District', 'quan-dong-da', '01'),
            array('007', 'Quận Hai Bà Trưng', 'Hai Ba Trung District', 'Quận Hai Bà Trưng', 'Hai Ba Trung District', 'quan-hai-ba-trung', '01'),
            array('008', 'Quận Hoàng Mai', 'Hoang Mai District', 'Quận Hoàng Mai', 'Hoang Mai District', 'quan-hoang-mai', '01'),
            array('009', 'Quận Thanh Xuân', 'Thanh Xuan District', 'Quận Thanh Xuân', 'Thanh Xuan District', 'quan-thanh-xuan', '01'),
            
            // Da Nang districts
            array('490', 'Quận Hải Châu', 'Hai Chau District', 'Quận Hải Châu', 'Hai Chau District', 'quan-hai-chau', '48'),
            array('491', 'Quận Thanh Khê', 'Thanh Khe District', 'Quận Thanh Khê', 'Thanh Khe District', 'quan-thanh-khe', '48'),
            array('492', 'Quận Sơn Trà', 'Son Tra District', 'Quận Sơn Trà', 'Son Tra District', 'quan-son-tra', '48'),
            array('493', 'Quận Ngũ Hành Sơn', 'Ngu Hanh Son District', 'Quận Ngũ Hành Sơn', 'Ngu Hanh Son District', 'quan-ngu-hanh-son', '48'),
            array('494', 'Quận Liên Chiểu', 'Lien Chieu District', 'Quận Liên Chiểu', 'Lien Chieu District', 'quan-lien-chieu', '48'),
            array('495', 'Quận Cẩm Lệ', 'Cam Le District', 'Quận Cẩm Lệ', 'Cam Le District', 'quan-cam-le', '48'),
        );
        
        foreach ($districts as $district) {
            $wpdb->replace($table_name, array(
                'code' => $district[0],
                'name' => $district[1],
                'name_en' => $district[2],
                'full_name' => $district[3],
                'full_name_en' => $district[4],
                'code_name' => $district[5],
                'province_code' => $district[6]
            ));
        }
    }
    
    private function populate_vietnam_wards() {
        global $wpdb;
        
        // Check if already populated
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aqm_wards");
        if ($count > 0) return;
        
        $table_name = $wpdb->prefix . 'aqm_wards';
        
        // Sample wards for District 1, Ho Chi Minh City
        $wards = array(
            array('26734', 'Phường Tân Định', 'Tan Dinh Ward', 'Phường Tân Định', 'Tan Dinh Ward', 'phuong-tan-dinh', '760'),
            array('26735', 'Phường Đa Kao', 'Da Kao Ward', 'Phường Đa Kao', 'Da Kao Ward', 'phuong-da-kao', '760'),
            array('26736', 'Phường Bến Nghé', 'Ben Nghe Ward', 'Phường Bến Nghé', 'Ben Nghe Ward', 'phuong-ben-nghe', '760'),
            array('26737', 'Phường Bến Thành', 'Ben Thanh Ward', 'Phường Bến Thành', 'Ben Thanh Ward', 'phuong-ben-thanh', '760'),
            array('26738', 'Phường Nguyễn Thái Bình', 'Nguyen Thai Binh Ward', 'Phường Nguyễn Thái Bình', 'Nguyen Thai Binh Ward', 'phuong-nguyen-thai-binh', '760'),
            array('26739', 'Phường Phạm Ngũ Lão', 'Pham Ngu Lao Ward', 'Phường Phạm Ngũ Lão', 'Pham Ngu Lao Ward', 'phuong-pham-ngu-lao', '760'),
            array('26740', 'Phường Cầu Ông Lãnh', 'Cau Ong Lanh Ward', 'Phường Cầu Ông Lãnh', 'Cau Ong Lanh Ward', 'phuong-cau-ong-lanh', '760'),
            array('26741', 'Phường Cô Giang', 'Co Giang Ward', 'Phường Cô Giang', 'Co Giang Ward', 'phuong-co-giang', '760'),
            array('26742', 'Phường Nguyễn Cư Trinh', 'Nguyen Cu Trinh Ward', 'Phường Nguyễn Cư Trinh', 'Nguyen Cu Trinh Ward', 'phuong-nguyen-cu-trinh', '760'),
            array('26743', 'Phường Cầu Kho', 'Cau Kho Ward', 'Phường Cầu Kho', 'Cau Kho Ward', 'phuong-cau-kho', '760'),
            
            // Sample wards for Ba Dinh District, Hanoi
            array('00001', 'Phường Phúc Xá', 'Phuc Xa Ward', 'Phường Phúc Xá', 'Phuc Xa Ward', 'phuong-phuc-xa', '001'),
            array('00002', 'Phường Trúc Bạch', 'Truc Bach Ward', 'Phường Trúc Bạch', 'Truc Bach Ward', 'phuong-truc-bach', '001'),
            array('00003', 'Phường Vĩnh Phúc', 'Vinh Phuc Ward', 'Phường Vĩnh Phúc', 'Vinh Phuc Ward', 'phuong-vinh-phuc', '001'),
            array('00004', 'Phường Cống Vị', 'Cong Vi Ward', 'Phường Cống Vị', 'Cong Vi Ward', 'phuong-cong-vi', '001'),
            array('00005', 'Phường Liễu Giai', 'Lieu Giai Ward', 'Phường Liễu Giai', 'Lieu Giai Ward', 'phuong-lieu-giai', '001'),
        );
        
        foreach ($wards as $ward) {
            $wpdb->replace($table_name, array(
                'code' => $ward[0],
                'name' => $ward[1],
                'name_en' => $ward[2],
                'full_name' => $ward[3],
                'full_name_en' => $ward[4],
                'code_name' => $ward[5],
                'district_code' => $ward[6]
            ));
        }
    }
    
    private function create_sample_campaign_with_gifts() {
        global $wpdb;
        
        // Check if sample campaign already exists
        $existing = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}aqm_campaigns WHERE title = 'Demo Campaign with Gifts'");
        if ($existing) return;
        
        // Create sample campaign
        $campaign_data = array(
            'title' => 'Demo Campaign with Gifts',
            'description' => 'Sample campaign to demonstrate gift management functionality',
            'status' => 'active',
            'start_date' => current_time('mysql'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'max_participants' => 1000,
            'created_by' => get_current_user_id(),
            'settings' => json_encode(array('enable_gifts' => true))
        );
        
        $wpdb->insert($wpdb->prefix . 'aqm_campaigns', $campaign_data);
        $campaign_id = $wpdb->insert_id;
        
        if ($campaign_id) {
            $this->create_sample_questions($campaign_id);
            $this->create_sample_gifts($campaign_id);
        }
    }
    
    private function create_sample_questions($campaign_id) {
        global $wpdb;
        
        $questions = array(
            array(
                'campaign_id' => $campaign_id,
                'question_text' => 'Họ và tên của bạn?',
                'question_type' => 'text',
                'is_required' => 1,
                'order_index' => 1,
                'points' => 0,
                'question_group' => 'Thông tin cá nhân'
            ),
            array(
                'campaign_id' => $campaign_id,
                'question_text' => 'Bạn đang sống ở tỉnh/thành phố nào?',
                'question_type' => 'provinces',
                'is_required' => 1,
                'order_index' => 2,
                'points' => 10,
                'options' => json_encode(array('load_districts' => true, 'load_wards' => true)),
                'question_group' => 'Địa chỉ'
            ),
            array(
                'campaign_id' => $campaign_id,
                'question_text' => 'Bạn thích loại sản phẩm nào?',
                'question_type' => 'multiple_choice',
                'is_required' => 1,
                'order_index' => 3,
                'points' => 20,
                'options' => json_encode(array(
                    'choices' => array('Công nghệ', 'Thời trang', 'Đồ ăn', 'Du lịch', 'Thể thao'),
                    'correct' => array(0, 1, 2, 3, 4) // All answers are correct
                )),
                'gift_eligibility' => 1,
                'question_group' => 'Sở thích'
            ),
            array(
                'campaign_id' => $campaign_id,
                'question_text' => 'Đánh giá trải nghiệm (1-5 sao)',
                'question_type' => 'rating',
                'is_required' => 1,
                'order_index' => 4,
                'points' => 20,
                'options' => json_encode(array('max_rating' => 5, 'icon' => 'star')),
                'gift_eligibility' => 1,
                'question_group' => 'Đánh giá'
            )
        );
        
        foreach ($questions as $question) {
            $wpdb->insert($wpdb->prefix . 'aqm_questions', $question);
        }
    }
    
    private function create_sample_gifts($campaign_id) {
        global $wpdb;
        
        $gifts = array(
            array(
                'campaign_id' => $campaign_id,
                'gift_name' => 'Voucher 100K',
                'gift_type' => 'voucher',
                'gift_value' => '100,000 VND',
                'description' => 'Voucher mua sắm trị giá 100,000 VND',
                'quantity_total' => 100,
                'quantity_remaining' => 100,
                'min_score' => 80,
                'max_score' => 100,
                'probability' => 15.00,
                'is_active' => 1,
                'valid_until' => date('Y-m-d H:i:s', strtotime('+60 days')),
                'gift_code_prefix' => 'VOUCHER100'
            ),
            array(
                'campaign_id' => $campaign_id,
                'gift_name' => 'Voucher 50K',
                'gift_type' => 'voucher',
                'gift_value' => '50,000 VND',
                'description' => 'Voucher mua sắm trị giá 50,000 VND',
                'quantity_total' => 200,
                'quantity_remaining' => 200,
                'min_score' => 60,
                'max_score' => 100,
                'probability' => 25.00,
                'is_active' => 1,
                'valid_until' => date('Y-m-d H:i:s', strtotime('+60 days')),
                'gift_code_prefix' => 'VOUCHER50'
            ),
            array(
                'campaign_id' => $campaign_id,
                'gift_name' => 'Giảm giá 10%',
                'gift_type' => 'discount',
                'gift_value' => '10%',
                'description' => 'Mã giảm giá 10% cho lần mua hàng tiếp theo',
                'quantity_total' => 0, // Unlimited
                'quantity_remaining' => 0,
                'min_score' => 40,
                'max_score' => 100,
                'probability' => 40.00,
                'is_active' => 1,
                'valid_until' => date('Y-m-d H:i:s', strtotime('+30 days')),
                'gift_code_prefix' => 'DISCOUNT10'
            ),
            array(
                'campaign_id' => $campaign_id,
                'gift_name' => 'Điểm thưởng',
                'gift_type' => 'points',
                'gift_value' => '500 points',
                'description' => '500 điểm thưởng tích lũy',
                'quantity_total' => 0, // Unlimited
                'quantity_remaining' => 0,
                'min_score' => 20,
                'max_score' => 100,
                'probability' => 50.00,
                'is_active' => 1,
                'valid_until' => date('Y-m-d H:i:s', strtotime('+90 days')),
                'gift_code_prefix' => 'POINTS'
            )
        );
        
        foreach ($gifts as $gift) {
            $wpdb->insert($wpdb->prefix . 'aqm_gifts', $gift);
        }
    }
    
    public function get_installation_status() {
        $missing_tables = $this->get_missing_tables();
        
        return array(
            'tables_status' => empty($missing_tables) ? 'complete' : 'missing',
            'missing_tables' => $missing_tables,
            'provinces_count' => $this->get_table_count('aqm_provinces'),
            'districts_count' => $this->get_table_count('aqm_districts'),
            'wards_count' => $this->get_table_count('aqm_wards'),
            'gifts_count' => $this->get_table_count('aqm_gifts')
        );
    }
    
    private function get_table_count($table_name) {
        global $wpdb;
        $full_table_name = $wpdb->prefix . $table_name;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") != $full_table_name) {
            return 0;
        }
        
        return intval($wpdb->get_var("SELECT COUNT(*) FROM $full_table_name"));
    }
}

// Initialize Enhanced Database
new AQM_Enhanced_Database();