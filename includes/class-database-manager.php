<?php
/**
 * ASTMED LMS Database Manager
 * Özel tabloları oluşturur ve yönetir
 */

if (!defined('ABSPATH')) exit;

class ASTMED_LMS_Database_Manager {
    
    /**
     * Tüm tabloları oluştur
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabloları oluştur
        self::create_enrollments_table($charset_collate);
        self::create_progress_table($charset_collate);
        self::create_quiz_attempts_table($charset_collate);
        self::create_certificates_table($charset_collate);
        self::create_subscriptions_table($charset_collate);
        self::create_transactions_table($charset_collate);
        self::create_notifications_table($charset_collate);
        self::create_user_activities_table($charset_collate);
        self::create_course_analytics_table($charset_collate);
        
        // Varsayılan verileri ekle
        self::insert_default_data();
        
        astmed_lms_log('Database tables created successfully');
    }
    
    /**
     * Kurs kayıtları tablosu
     */
    private static function create_enrollments_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astmed_enrollments';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            enrollment_type enum('free', 'purchase', 'subscription', 'admin') NOT NULL DEFAULT 'free',
            enrollment_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completion_date datetime NULL,
            status enum('active', 'completed', 'cancelled', 'expired') NOT NULL DEFAULT 'active',
            progress_percentage decimal(5,2) NOT NULL DEFAULT 0.00,
            payment_id bigint(20) unsigned NULL,
            subscription_id bigint(20) unsigned NULL,
            notes text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_enrollment (user_id, course_id),
            KEY idx_user_id (user_id),
            KEY idx_course_id (course_id),
            KEY idx_status (status),
            KEY idx_enrollment_date (enrollment_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Ders ilerlemesi tablosu
     */
    private static function create_progress_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astmed_progress';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            lesson_id bigint(20) unsigned NOT NULL,
            status enum('not_started', 'in_progress', 'completed') NOT NULL DEFAULT 'not_started',
            progress_percentage decimal(5,2) NOT NULL DEFAULT 0.00,
            time_spent int(11) NOT NULL DEFAULT 0,
            last_position varchar(255) NULL,
            started_at datetime NULL,
            completed_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_progress (user_id, lesson_id),
            KEY idx_user_course (user_id, course_id),
            KEY idx_lesson_id (lesson_id),
            KEY idx_status (status)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Quiz denemeleri tablosu
     */
    private static function create_quiz_attempts_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astmed_quiz_attempts';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            quiz_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            lesson_id bigint(20) unsigned NULL,
            attempt_number int(11) NOT NULL DEFAULT 1,
            answers longtext NOT NULL,
            score decimal(5,2) NOT NULL DEFAULT 0.00,
            max_score decimal(5,2) NOT NULL DEFAULT 100.00,
            percentage decimal(5,2) NOT NULL DEFAULT 0.00,
            time_taken int(11) NOT NULL DEFAULT 0,
            status enum('in_progress', 'completed', 'abandoned') NOT NULL DEFAULT 'in_progress',
            passed tinyint(1) NOT NULL DEFAULT 0,
            started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_quiz (user_id, quiz_id),
            KEY idx_course_id (course_id),
            KEY idx_status (status),
            KEY idx_started_at (started_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Verilen sertifikalar tablosu
     */
    private static function create_certificates_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astmed_certificates';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            certificate_number varchar(50) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            course_id bigint(20) unsigned NOT NULL,
            template_id bigint(20) unsigned NULL,
            certificate_data longtext NOT NULL,
            file_path varchar(255) NULL,
            download_count int(11) NOT NULL DEFAULT 0,
            status enum('active', 'revoked', 'expired') NOT NULL DEFAULT 'active',
            issued_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_certificate_number (certificate_number),
            UNIQUE KEY unique_user_course (user_id, course_id),
            KEY idx_user_id (user_id),
            KEY idx_course_id (course_id),
            KEY idx_status (status),
            KEY idx_issued_at (issued_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Kullanıcı abonelikleri tablosu
     */
    private static function create_subscriptions_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astmed_subscriptions';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            plan_id bigint(20) unsigned NOT NULL,
            status enum('active', 'cancelled', 'expired', 'suspended', 'trial') NOT NULL DEFAULT 'active',
            payment_method varchar(50) NULL,
            payment_id varchar(255) NULL,
            trial_ends_at datetime NULL,
            current_period_start datetime NOT NULL,
            current_period_end datetime NOT NULL,
            cancelled_at datetime NULL,
            cancel_reason text NULL,
            auto_renew tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_plan_id (plan_id),
            KEY idx_status (status),
            KEY idx_period_end (current_period_end)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Ödemeler tablosu
     */
    private static function create_transactions_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astmed_transactions';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            transaction_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            type enum('course_purchase', 'subscription', 'subscription_renewal', 'refund') NOT NULL,
            item_id bigint(20) unsigned NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'TRY',
            payment_method varchar(50) NOT NULL,
            payment_gateway varchar(50) NOT NULL,
            gateway_transaction_id varchar(255) NULL,
            status enum('pending', 'completed', 'failed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
            gateway_response longtext NULL,
            invoice_number varchar(50) NULL,
            refund_amount decimal(10,2) NULL DEFAULT 0.00,
            notes text NULL,
            processed_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_transaction_id (transaction_id),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_type (type),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Bildirimler tablosu
     */
    private static function create_notifications_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astmed_notifications';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data longtext NULL,
            is_read tinyint(1) NOT NULL DEFAULT 0,
            action_url varchar(255) NULL,
            expires_at datetime NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            read_at datetime NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_type (type),
            KEY idx_is_read (is_read),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Kullanıcı aktiviteleri tablosu
     */
    private static function create_user_activities_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astmed_user_activities';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            activity_type varchar(50) NOT NULL,
            object_id bigint(20) unsigned NULL,
            object_type varchar(50) NULL,
            activity_data longtext NULL,
            ip_address varchar(45) NULL,
            user_agent text NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_activity_type (activity_type),
            KEY idx_object (object_id, object_type),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Kurs analitikleri tablosu
     */
    private static function create_course_analytics_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astmed_course_analytics';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned NOT NULL,
            date date NOT NULL,
            views int(11) NOT NULL DEFAULT 0,
            enrollments int(11) NOT NULL DEFAULT 0,
            completions int(11) NOT NULL DEFAULT 0,
            revenue decimal(10,2) NOT NULL DEFAULT 0.00,
            avg_completion_time int(11) NOT NULL DEFAULT 0,
            avg_rating decimal(3,2) NOT NULL DEFAULT 0.00,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_course_date (course_id, date),
            KEY idx_course_id (course_id),
            KEY idx_date (date)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
    
    /**
     * Varsayılan verileri ekle
     */
    private static function insert_default_data() {
        // Varsayılan zorluk seviyelerini ekle
        $difficulties = array(
            array('name' => 'Başlangıç', 'slug' => 'baslangic'),
            array('name' => 'Orta', 'slug' => 'orta'),
            array('name' => 'İleri', 'slug' => 'ileri'),
            array('name' => 'Uzman', 'slug' => 'uzman')
        );
        
        foreach ($difficulties as $difficulty) {
            if (!term_exists($difficulty['slug'], 'course_difficulty')) {
                wp_insert_term($difficulty['name'], 'course_difficulty', array(
                    'slug' => $difficulty['slug']
                ));
            }
        }
        
        // Varsayılan kurs kategorilerini ekle
        $categories = array(
            array('name' => 'Elektronörofizyoloji', 'slug' => 'enf'),
            array('name' => 'İntraoperatif Monitörizasyon', 'slug' => 'ionm'),
            array('name' => 'EEG Analizi', 'slug' => 'eeg'),
            array('name' => 'EMG Teknikleri', 'slug' => 'emg'),
            array('name' => 'Vaka Analizleri', 'slug' => 'vaka-analizleri')
        );
        
        foreach ($categories as $category) {
            if (!term_exists($category['slug'], 'course_category')) {
                wp_insert_term($category['name'], 'course_category', array(
                    'slug' => $category['slug']
                ));
            }
        }
        
        astmed_lms_log('Default data inserted successfully');
    }
    
    /**
     * Tabloları temizle (uninstall için)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'astmed_enrollments',
            $wpdb->prefix . 'astmed_progress',
            $wpdb->prefix . 'astmed_quiz_attempts',
            $wpdb->prefix . 'astmed_certificates',
            $wpdb->prefix . 'astmed_subscriptions',
            $wpdb->prefix . 'astmed_transactions',
            $wpdb->prefix . 'astmed_notifications',
            $wpdb->prefix . 'astmed_user_activities',
            $wpdb->prefix . 'astmed_course_analytics'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        astmed_lms_log('Database tables dropped');
    }
    
    /**
     * Tablo mevcut mu kontrol et
     */
    public static function table_exists($table_name) {
        global $wpdb;
        
        $table = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->prefix . $table_name
        ));
        
        return $table === $wpdb->prefix . $table_name;
    }
    
    /**
     * Veritabanı verilerini temizle (test için)
     */
    public static function clear_test_data() {
        global $wpdb;
        
        // Test verilerini temizle
        $tables = array(
            'astmed_enrollments',
            'astmed_progress', 
            'astmed_quiz_attempts',
            'astmed_certificates',
            'astmed_user_activities'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$table}");
        }
        
        astmed_lms_log('Test data cleared');
    }
    
    /**
     * Veritabanı optimizasyonu
     */
    public static function optimize_tables() {
        global $wpdb;
        
        $tables = array(
            'astmed_enrollments',
            'astmed_progress',
            'astmed_quiz_attempts', 
            'astmed_certificates',
            'astmed_subscriptions',
            'astmed_transactions',
            'astmed_notifications',
            'astmed_user_activities',
            'astmed_course_analytics'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}{$table}");
        }
        
        astmed_lms_log('Database tables optimized');
    }
    
    /**
     * İndeksleri yeniden oluştur
     */
    public static function rebuild_indexes() {
        global $wpdb;
        
        // Kritik indeksler
        $indexes = array(
            'astmed_enrollments' => array(
                'UNIQUE KEY unique_enrollment (user_id, course_id)',
                'KEY idx_user_status (user_id, status)',
                'KEY idx_course_status (course_id, status)'
            ),
            'astmed_progress' => array(
                'UNIQUE KEY unique_progress (user_id, lesson_id)',
                'KEY idx_user_course_status (user_id, course_id, status)'
            ),
            'astmed_quiz_attempts' => array(
                'KEY idx_user_quiz_attempt (user_id, quiz_id, attempt_number)',
                'KEY idx_course_completed (course_id, completed_at)'
            )
        );
        
        foreach ($indexes as $table => $table_indexes) {
            foreach ($table_indexes as $index) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}{$table} ADD $index");
            }
        }
        
        astmed_lms_log('Database indexes rebuilt');
    }
}