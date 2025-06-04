<?php
/**
 * ASTMED LMS Core Class
 * Tüm sistemin kalbi - Singleton pattern ile tek instance
 */

if (!defined('ABSPATH')) exit;

final class ASTMED_LMS_Core {
    
    private static $instance = null;
    private $modules = array();
    private $initialized = false;
    
    // Ana modül yöneticileri
    private $post_types;
    private $roles_manager;
    private $subscription_manager;
    private $payment_manager;
    private $access_manager;
    private $progress_manager;
    private $certificate_manager;
    private $reporting_manager;
    private $template_loader;
    private $admin_manager;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - private for singleton
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
        $this->init_modules();
    }
    
    /**
     * WordPress hook'larını başlat
     */
    private function init_hooks() {
        add_action('init', array($this, 'init_system'), 0);
        add_action('wp_loaded', array($this, 'system_loaded'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Tüm bağımlılıkları yükle
     */
    private function load_dependencies() {
        $includes = array(
            // Temel sınıflar
            'includes/class-post-types.php',
            'includes/class-roles-manager.php',
            'includes/class-database-manager.php',
            'includes/class-template-loader.php',
            
            // İş mantığı modülleri
            'includes/modules/class-subscription-manager.php',
            'includes/modules/class-payment-manager.php',
            'includes/modules/class-access-manager.php',
            'includes/modules/class-progress-manager.php',
            'includes/modules/class-certificate-manager.php',
            'includes/modules/class-reporting-manager.php',
            'includes/modules/class-notification-manager.php',
            
            // Admin paneli
            'includes/admin/class-admin-manager.php',
            'includes/admin/class-admin-courses.php',
            'includes/admin/class-admin-users.php',
            'includes/admin/class-admin-sales.php',
            'includes/admin/class-admin-reports.php',
            'includes/admin/class-admin-settings.php',
            
            // Frontend
            'includes/frontend/class-shortcodes.php',
            'includes/frontend/class-user-dashboard.php',
            'includes/frontend/class-course-display.php',
            'includes/frontend/class-checkout-manager.php',
            
            // API & Entegrasyonlar
            'includes/api/class-rest-api.php',
            'includes/integrations/class-woocommerce-integration.php',
            'includes/integrations/class-payment-integrations.php',
            
            // Yardımcı sınıflar
            'includes/helpers/class-course-helper.php',
            'includes/helpers/class-user-helper.php',
            'includes/helpers/class-pricing-helper.php',
            'includes/helpers/class-email-helper.php',
        );
        
        foreach ($includes as $file) {
            $path = ASTMED_LMS_PLUGIN_DIR . $file;
            if (file_exists($path)) {
                require_once $path;
            } else {
                astmed_lms_log("Missing file: " . $file, 'error');
            }
        }
    }
    
    /**
     * Tüm modülleri başlat
     */
    private function init_modules() {
        // Temel sınıfları başlat
        $this->post_types = new ASTMED_LMS_Post_Types();
        $this->roles_manager = new ASTMED_LMS_Roles_Manager();
        $this->template_loader = new ASTMED_LMS_Template_Loader();
        
        // İş mantığı modülleri
        $this->subscription_manager = new ASTMED_LMS_Subscription_Manager();
        $this->payment_manager = new ASTMED_LMS_Payment_Manager();
        $this->access_manager = new ASTMED_LMS_Access_Manager();
        $this->progress_manager = new ASTMED_LMS_Progress_Manager();
        $this->certificate_manager = new ASTMED_LMS_Certificate_Manager();
        $this->reporting_manager = new ASTMED_LMS_Reporting_Manager();
        
        // Admin & Frontend
        if (is_admin()) {
            $this->admin_manager = new ASTMED_LMS_Admin_Manager();
        } else {
            new ASTMED_LMS_Shortcodes();
            new ASTMED_LMS_User_Dashboard();
            new ASTMED_LMS_Course_Display();
        }
        
        // API'leri başlat
        new ASTMED_LMS_REST_API();
        
        // Entegrasyonları kontrol et ve başlat
        if (class_exists('WooCommerce')) {
            new ASTMED_LMS_WooCommerce_Integration();
        }
    }
    
    /**
     * Sistem başlatma
     */
    public function init_system() {
        if ($this->initialized) return;
        
        // Veritabanı kontrolü ve kurulum
        $this->check_database();
        
        // Rewrite rules'ları flush et (sadece gerektiğinde)
        if (get_option('astmed_lms_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('astmed_lms_flush_rewrite_rules');
        }
        
        $this->initialized = true;
        do_action('astmed_lms_initialized');
    }
    
    /**
     * Sistem tamamen yüklendiğinde
     */
    public function system_loaded() {
        do_action('astmed_lms_loaded');
    }
    
    /**
     * Admin panel başlatma
     */
    public function admin_init() {
        do_action('astmed_lms_admin_init');
    }
    
    /**
     * Frontend CSS/JS yükleme
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'astmed-lms-frontend',
            ASTMED_LMS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            ASTMED_LMS_VERSION
        );
        
        wp_enqueue_script(
            'astmed-lms-frontend',
            ASTMED_LMS_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            ASTMED_LMS_VERSION,
            true
        );
        
        // AJAX için localize
        wp_localize_script('astmed-lms-frontend', 'astmed_lms_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('astmed_lms_nonce'),
            'strings' => array(
                'loading' => __('Yükleniyor...', 'astmed-lms'),
                'error' => __('Bir hata oluştu.', 'astmed-lms'),
                'success' => __('İşlem başarılı.', 'astmed-lms'),
            )
        ));
    }
    
    /**
     * Admin CSS/JS yükleme
     */
    public function enqueue_admin_assets($hook) {
        // Sadece ASTMED LMS sayfalarında yükle
        if (strpos($hook, 'astmed-lms') === false) return;
        
        wp_enqueue_style(
            'astmed-lms-admin',
            ASTMED_LMS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ASTMED_LMS_VERSION
        );
        
        wp_enqueue_script(
            'astmed-lms-admin',
            ASTMED_LMS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            ASTMED_LMS_VERSION,
            true
        );
        
        wp_localize_script('astmed-lms-admin', 'astmed_lms_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('astmed_lms_admin_nonce'),
        ));
    }
    
    /**
     * Veritabanı kontrolü
     */
    private function check_database() {
        $db_version = get_option('astmed_lms_db_version', '0');
        
        if (version_compare($db_version, ASTMED_LMS_VERSION, '<')) {
            require_once ASTMED_LMS_PLUGIN_DIR . 'includes/class-database-manager.php';
            ASTMED_LMS_Database_Manager::create_tables();
            update_option('astmed_lms_db_version', ASTMED_LMS_VERSION);
        }
    }
    
    /**
     * Plugin aktivasyon
     */
    public static function activate() {
        // Gerekli tabloları oluştur
        require_once ASTMED_LMS_PLUGIN_DIR . 'includes/class-database-manager.php';
        ASTMED_LMS_Database_Manager::create_tables();
        
        // Varsayılan rolleri oluştur
        require_once ASTMED_LMS_PLUGIN_DIR . 'includes/class-roles-manager.php';
        ASTMED_LMS_Roles_Manager::create_roles();
        
        // Varsayılan ayarları ekle
        self::set_default_options();
        
        // Rewrite rules'ı flush et
        add_option('astmed_lms_flush_rewrite_rules', '1');
        
        astmed_lms_log('ASTMED LMS activated successfully');
    }
    
    /**
     * Plugin deaktivasyon
     */
    public static function deactivate() {
        // Cache temizle
        wp_cache_flush();
        
        astmed_lms_log('ASTMED LMS deactivated');
    }
    
    /**
     * Varsayılan ayarları belirle
     */
    private static function set_default_options() {
        $defaults = array(
            'astmed_lms_currency' => 'TRY',
            'astmed_lms_currency_symbol' => '₺',
            'astmed_lms_enable_certificates' => 'yes',
            'astmed_lms_enable_subscriptions' => 'yes',
            'astmed_lms_certificate_template' => 'default',
            'astmed_lms_payment_methods' => array('stripe', 'iyzico'),
            'astmed_lms_email_notifications' => 'yes',
        );
        
        foreach ($defaults as $option => $value) {
            if (!get_option($option)) {
                add_option($option, $value);
            }
        }
    }
    
    // Getter metodları
    public function get_subscription_manager() { return $this->subscription_manager; }
    public function get_payment_manager() { return $this->payment_manager; }
    public function get_access_manager() { return $this->access_manager; }
    public function get_progress_manager() { return $this->progress_manager; }
    public function get_certificate_manager() { return $this->certificate_manager; }
    public function get_reporting_manager() { return $this->reporting_manager; }
    public function get_template_loader() { return $this->template_loader; }
    public function get_admin_manager() { return $this->admin_manager; }
}