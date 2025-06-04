<?php
/*
Plugin Name: ASTMED Academy LMS Pro
Plugin URI: https://astmedacademy.com
Description: Elektronörofizyoloji ve İntraoperatif Nöromonitörizasyon alanında özelleşmiş, ticari LMS platformu. Kurs satışı, abonelik yönetimi, sertifika üretimi ve kurumsal çözümler.
Version: 2.0.0
Author: ASTMED Academy Development Team
Author URI: https://astmedacademy.com
Text Domain: astmed-lms
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
License: Proprietary
Network: false
*/

// Güvenlik: Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Plugin sabitleri
define('ASTMED_LMS_VERSION', '2.0.0');
define('ASTMED_LMS_PLUGIN_FILE', __FILE__);
define('ASTMED_LMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ASTMED_LMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASTMED_LMS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Minimum gereksinimler kontrolü
register_activation_hook(__FILE__, 'astmed_lms_check_requirements');

function astmed_lms_check_requirements() {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('ASTMED LMS requires PHP 7.4 or higher. Your current version: ' . PHP_VERSION, 'astmed-lms'));
    }
    
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('ASTMED LMS requires WordPress 5.0 or higher.', 'astmed-lms'));
    }
}

// Ana yükleyici sınıfını dahil et
require_once ASTMED_LMS_PLUGIN_DIR . 'includes/class-lms-core.php';

// Plugin başlatma
function astmed_lms_init() {
    // Dil dosyalarını yükle
    load_plugin_textdomain('astmed-lms', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Ana sınıfı başlat
    ASTMED_LMS_Core::get_instance();
}
add_action('plugins_loaded', 'astmed_lms_init');

// Aktivasyon hook'ları
register_activation_hook(__FILE__, array('ASTMED_LMS_Core', 'activate'));
register_deactivation_hook(__FILE__, array('ASTMED_LMS_Core', 'deactivate'));

// Debug modu (development için)
if (defined('WP_DEBUG') && WP_DEBUG) {
    define('ASTMED_LMS_DEBUG', true);
} else {
    define('ASTMED_LMS_DEBUG', false);
}

// Global yardımcı fonksiyonlar
function astmed_lms_get_template($template_name, $args = array(), $template_path = '') {
    return ASTMED_LMS_Core::get_instance()->get_template_loader()->get_template($template_name, $args, $template_path);
}

function astmed_lms_log($message, $level = 'info') {
    if (ASTMED_LMS_DEBUG) {
        error_log('[ASTMED LMS] ' . $message);
    }
}

function astmed_lms_get_user_subscription($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    return ASTMED_LMS_Core::get_instance()->get_subscription_manager()->get_user_subscription($user_id);
}

function astmed_lms_user_can_access_course($course_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    return ASTMED_LMS_Core::get_instance()->get_access_manager()->user_can_access_course($course_id, $user_id);
}

function astmed_lms_get_course_progress($course_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    return ASTMED_LMS_Core::get_instance()->get_progress_manager()->get_course_progress($course_id, $user_id);
}