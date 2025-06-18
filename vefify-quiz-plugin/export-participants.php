<?php
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

global $wpdb;
$data = $wpdb->get_results("SELECT * FROM wp_vefify_participants ORDER BY created_at DESC", ARRAY_A);

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="participants.csv"');

$output = fopen('php://output', 'w');
if (!empty($data)) {
    fputcsv($output, array_keys($data[0]));
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
}
fclose($output);
?>