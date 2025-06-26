
<?php
define('USE_TEST_MODE', true);
define('TEST_DATETIME', '2025-06-08 13:30:00');

function get_now($format = 'Y-m-d H:i:s') {
    return USE_TEST_MODE ? date($format, strtotime(TEST_DATETIME)) : date($format);
}

function get_day_of_year() {
    return USE_TEST_MODE ? date('z', strtotime(TEST_DATETIME)) + 1 : date('z') + 1;
}
?>