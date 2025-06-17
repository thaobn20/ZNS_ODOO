<?php
/**
 * Configuration File
 * File: config/settings.php
 */

// Default plugin settings
$vefify_quiz_defaults = [
    'default_questions_per_quiz' => 5,
    'default_time_limit' => 600, // 10 minutes in seconds
    'default_pass_score' => 3,
    'enable_retakes' => false,
    'phone_number_required' => true,
    'province_required' => true,
    'pharmacy_code_required' => false,
    'enable_analytics' => true,
    'enable_gift_api' => false,
    'gift_api_timeout' => 30,
    'max_participants_per_campaign' => 10000
];

// Vietnamese provinces
$vietnamese_provinces = [
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
];

// Gift types
$gift_types = [
    'voucher' => 'Voucher/Cash',
    'discount' => 'Discount Code',
    'product' => 'Physical Product',
    'points' => 'Loyalty Points',
    'certificate' => 'Certificate'
];

// Question categories
$question_categories = [
    'medication' => 'Medication & Drugs',
    'nutrition' => 'Nutrition & Diet',
    'safety' => 'Health & Safety',
    'hygiene' => 'Hygiene & Prevention',
    'symptoms' => 'Symptoms & Diagnosis',
    'wellness' => 'General Wellness',
    'pharmacy' => 'Pharmacy Services'
];

/**
 * Helper function to get plugin settings
 */
function vefify_get_setting($key, $default = null) {
    global $vefify_quiz_defaults;
    
    $settings = get_option('vefify_quiz_settings', []);
    
    if (isset($settings[$key])) {
        return $settings[$key];
    }
    
    if (isset($vefify_quiz_defaults[$key])) {
        return $vefify_quiz_defaults[$key];
    }
    
    return $default;
}

/**
 * Helper function to update plugin settings
 */
function vefify_update_setting($key, $value) {
    $settings = get_option('vefify_quiz_settings', []);
    $settings[$key] = $value;
    update_option('vefify_quiz_settings', $settings);
}