<?php
/**
 * Kullanıcı verilerini temizle
 */
function astmed_lms_cleanup_users() {
    global $wpdb;
    
    // LMS rollerini kaldır
    ASTMED_LMS_Roles_Manager::remove_roles();
    
    // LMS rolündeki kullanıcıları default role'a çevir
    $lms_users = get_users(array(
        'role__in' => array('astmed_student', 'astmed_instructor', 'astmed_admin', 'astmed_corporate_admin'),
        'fields' => 'ID'
    ));
    
    foreach ($lms_users as $user_id) {
        $user = new WP_User($user_id);
        
        // LMS rollerini kaldır
        $user->remove_role('astmed_student');
        $user->remove_role('astmed_instructor');
        $user->remove_role('astmed_admin');
        $user->remove_role('astmed_corporate_admin');
        
        // Eğer hiç rolü kalmazsa subscriber yap
        if (empty($user->roles)) {
            $user->add_role('subscriber');
        }
    }
    
    // Kullanıcı meta verilerini temizle
    $user_meta_keys = array(
        '_astmed_current_subscription',
        '_astmed_completed_lessons',
        '_astmed_quiz_attempts',
        '_astmed_certificates',
        '_astmed_last_activity',
        '_astmed_total_learning_time',
        '_astmed_courses_completed',
        'last_login',
        'astmed_notifications_read'
    );
    
    foreach ($user_meta_keys as $meta_key) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
            $meta_key
        ));
    }
}

/**
 * Plugin ayarlarını temizle
 */
function astmed_lms_cleanup_options() {
    global $wpdb;
    
    // Tüm ASTMED LMS ayarlarını sil
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'astmed_lms_%'");
    
    // Spesifik ayarlar
    $options_to_delete = array(
        'astmed_lms_version',
        'astmed_lms_db_version',
        'astmed_lms_activation_date',
        'astmed_lms_roles_created',
        'astmed_lms_flush_rewrite_rules',
        'astmed_lms_currency',
        'astmed_lms_currency_symbol',
        'astmed_lms_enable_certificates',
        'astmed_lms_enable_subscriptions',
        'astmed_lms_certificate_template',
        'astmed_lms_payment_methods',
        'astmed_lms_email_notifications',
        'astmed_lms_stripe_public_key',
        'astmed_lms_stripe_secret_key',
        'astmed_lms_iyzico_api_key',
        'astmed_lms_iyzico_secret_key',
        'astmed_lms_delete_data_on_uninstall'
    );
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // Transient'ları temizle
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_astmed_%' OR option_name LIKE '_transient_timeout_astmed_%'");
}

/**
 * Dosyaları temizle
 */
function astmed_lms_cleanup_files() {
    
    // Upload klasöründeki LMS dosyalarını temizle
    $upload_dir = wp_upload_dir();
    $astmed_dir = $upload_dir['basedir'] . '/astmed-lms/';
    
    if (is_dir($astmed_dir)) {
        astmed_lms_delete_directory($astmed_dir);
    }
    
    // Sertifika dosyalarını temizle
    $certificates_dir = $upload_dir['basedir'] . '/certificates/';
    if (is_dir($certificates_dir)) {
        astmed_lms_delete_directory($certificates_dir);
    }
    
    // Log dosyalarını temizle
    $log_dir = $upload_dir['basedir'] . '/astmed-logs/';
    if (is_dir($log_dir)) {
        astmed_lms_delete_directory($log_dir);
    }
    
    // Cache dosyalarını temizle
    $cache_dir = WP_CONTENT_DIR . '/cache/astmed-lms/';
    if (is_dir($cache_dir)) {
        astmed_lms_delete_directory($cache_dir);
    }
}

/**
 * Cron job'ları temizle
 */
function astmed_lms_cleanup_cron() {
    
    // Scheduled cron job'ları kaldır
    $cron_hooks = array(
        'astmed_lms_check_subscription_renewals',
        'astmed_lms_process_expiring_subscriptions',
        'astmed_lms_cleanup_expired_sessions',
        'astmed_lms_send_digest_emails',
        'astmed_lms_process_notifications',
        'astmed_lms_cleanup_temp_files',
        'astmed_lms_backup_analytics'
    );
    
    foreach ($cron_hooks as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
        
        // Tüm bu hook'un örneklerini kaldır
        wp_clear_scheduled_hook($hook);
    }
}

/**
 * Klasörü ve içeriğini tamamen sil
 */
function astmed_lms_delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            astmed_lms_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

/**
 * WordPress kapasitelerini temizle
 */
function astmed_lms_cleanup_capabilities() {
    global $wp_roles;
    
    if (!isset($wp_roles)) {
        $wp_roles = new WP_Roles();
    }
    
    // Tüm LMS kapasitelerini tanımla
    $lms_capabilities = array(
        'astmed_access_courses',
        'astmed_create_courses',
        'astmed_edit_own_courses',
        'astmed_manage_all_courses',
        'astmed_delete_courses',
        'astmed_create_lessons',
        'astmed_edit_own_lessons',
        'astmed_manage_all_lessons',
        'astmed_delete_lessons',
        'astmed_create_quizzes',
        'astmed_edit_own_quizzes',
        'astmed_manage_all_quizzes',
        'astmed_delete_quizzes',
        'astmed_take_quizzes',
        'astmed_view_progress',
        'astmed_view_student_progress',
        'astmed_download_certificates',
        'astmed_manage_certificates',
        'astmed_comment_courses',
        'astmed_rate_courses',
        'astmed_grade_assignments',
        'astmed_manage_enrollments',
        'astmed_manage_team_enrollments',
        'astmed_view_team_progress',
        'astmed_export_team_reports',
        'astmed_manage_team_users',
        'astmed_bulk_operations',
        'astmed_manage_users',
        'astmed_manage_subscriptions',
        'astmed_manage_payments',
        'astmed_view_all_reports',
        'astmed_export_reports',
        'astmed_export_data',
        'astmed_system_settings',
        'astmed_communicate_students'
    );
    
    // Tüm rollerden bu kapasiteleri kaldır
    foreach ($wp_roles->roles as $role_name => $role_info) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($lms_capabilities as $cap) {
                $role->remove_cap($cap);
            }
        }
    }
}

/**
 * Veritabanı temizlik işlemlerini geri alınamaz şekilde onayla
 */
function astmed_lms_confirm_data_deletion() {
    
    // Son güvenlik kontrolü
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    // Admin onayı var mı kontrol et
    $confirmation = get_option('astmed_lms_uninstall_confirmed', false);
    
    if (!$confirmation) {
        // Veriyi koruma modunda - sadece ayarları temizle
        return false;
    }
    
    return true;
}

/**
 * Plugin network'te aktif ise network genelinde temizle
 */
function astmed_lms_cleanup_network() {
    
    if (!is_multisite()) {
        return;
    }
    
    $sites = get_sites(array('number' => 0));
    
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        
        // Her site için temizlik yap
        astmed_lms_cleanup_database();
        astmed_lms_cleanup_posts();
        astmed_lms_cleanup_users();
        astmed_lms_cleanup_options();
        astmed_lms_cleanup_files();
        
        restore_current_blog();
    }
    
    // Network ayarlarını temizle
    delete_site_option('astmed_lms_network_activated');
    delete_site_option('astmed_lms_network_version');
}

/**
 * Son temizlik kontrolü ve log
 */
function astmed_lms_final_cleanup() {
    
    // Cache temizle
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Object cache temizle
    if (function_exists('wp_cache_flush_group')) {
        wp_cache_flush_group('astmed_lms');
    }
    
    // Rewrite rules temizle
    flush_rewrite_rules();
    
    // Son log kaydı
    error_log('[ASTMED LMS] Uninstall completed successfully at ' . date('Y-m-d H:i:s'));
    
    // Son olarak log dosyasını da sil
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($log_file)) {
        $content = file_get_contents($log_file);
        $content = preg_replace('/\[ASTMED LMS\].*\n/', '', $content);
        file_put_contents($log_file, $content);
    }
}

// Ana uninstall işlemini başlat
if (astmed_lms_confirm_data_deletion()) {
    
    // Network kurulum kontrolü
    if (is_multisite()) {
        astmed_lms_cleanup_network();
    } else {
        astmed_lms_uninstall();
    }
    
    // Kapasiteleri temizle
    astmed_lms_cleanup_capabilities();
    
    // Son temizlik
    astmed_lms_final_cleanup();
    
} else {
    
    // Sadece güvenli temizlik - verileri koru
    astmed_lms_cleanup_options();
    astmed_lms_cleanup_cron();
    
    error_log('[ASTMED LMS] Plugin uninstalled but data preserved (safe mode)');
}
 * ASTMED LMS Uninstall Script
 * Plugin silindiğinde çalışır - tüm verileri temizler
 */

// WordPress uninstall kontrolü
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Plugin sabitlerini tanımla
define('ASTMED_LMS_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Gerekli sınıfları yükle
require_once ASTMED_LMS_PLUGIN_DIR . 'includes/class-database-manager.php';
require_once ASTMED_LMS_PLUGIN_DIR . 'includes/class-roles-manager.php';

/**
 * Tüm ASTMED LMS verilerini temizle
 */
function astmed_lms_uninstall() {
    
    // Kullanıcı onayı kontrolü (güvenlik için)
    $delete_data = get_option('astmed_lms_delete_data_on_uninstall', false);
    
    if (!$delete_data) {
        // Sadece plugin ayarlarını temizle, verileri koru
        astmed_lms_cleanup_options();
        return;
    }
    
    // TAMAMEN TEMİZLE
    astmed_lms_cleanup_database();
    astmed_lms_cleanup_posts();
    astmed_lms_cleanup_users();
    astmed_lms_cleanup_options();
    astmed_lms_cleanup_files();
    astmed_lms_cleanup_cron();
    
    // Log kaydı
    error_log('[ASTMED LMS] Plugin uninstalled and all data removed');
}

/**
 * Veritabanı tablolarını temizle
 */
function astmed_lms_cleanup_database() {
    global $wpdb;
    
    // Özel tabloları sil
    ASTMED_LMS_Database_Manager::drop_tables();
    
    // WordPress tablolarından LMS verilerini temizle
    
    // Post meta verilerini temizle
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_astmed_%' OR meta_key LIKE '_course_%' OR meta_key LIKE '_lesson_%' OR meta_key LIKE '_quiz_%' OR meta_key LIKE '_certificate_%' OR meta_key LIKE '_subscription_%'");
    
    // User meta verilerini temizle
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_astmed_%' OR meta_key LIKE 'astmed_%'");
    
    // Comment meta verilerini temizle
    $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE meta_key LIKE '_astmed_%' OR meta_key LIKE 'astmed_%'");
    
    // Term meta verilerini temizle
    $wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '_astmed_%' OR meta_key LIKE 'astmed_%'");
}

/**
 * Post türlerini ve içeriklerini temizle
 */
function astmed_lms_cleanup_posts() {
    global $wpdb;
    
    $post_types = array(
        'astmed_course',
        'astmed_lesson', 
        'astmed_quiz',
        'astmed_certificate',
        'astmed_subscription'
    );
    
    foreach ($post_types as $post_type) {
        // Bu post türündeki tüm postları al
        $posts = get_posts(array(
            'post_type' => $post_type,
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        // Her postu sil (meta verileri de silinir)
        foreach ($posts as $post) {
            wp_delete_post($post->ID, true);
        }
    }
    
    // Taksonomileri temizle
    $taxonomies = array('course_category', 'course_tag', 'course_difficulty');
    
    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false
        ));
        
        foreach ($terms as $term) {
            wp_delete_term($term->term_id, $taxonomy);
        }
        
        // Taksonomiyi kayıttan kaldır
        unregister_taxonomy($taxonomy);
    }
}

/**