<?php
/**
 * ASTMED LMS WooCommerce Integration
 * Kurs satışı, abonelik yönetimi ve otomatik erişim kontrolü
 */

if (!defined('ABSPATH')) exit;

class ASTMED_LMS_WooCommerce_Integration {
    
    public function __construct() {
        // WooCommerce kontrolü
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * WooCommerce yüklendiyse başlat
     */
    public function init() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->hooks();
        $this->init_product_types();
    }
    
    /**
     * Hook'ları bağla
     */
    private function hooks() {
        // Ürün tipleri
        add_filter('product_type_selector', array($this, 'add_course_product_types'));
        add_filter('woocommerce_product_data_tabs', array($this, 'add_course_product_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'course_product_tab_content'));
        add_action('woocommerce_process_product_meta', array($this, 'save_course_product_data'));
        
        // Sipariş işlemleri
        add_action('woocommerce_order_status_completed', array($this, 'on_order_completed'));
        add_action('woocommerce_order_status_processing', array($this, 'on_order_processing'));
        add_action('woocommerce_order_status_cancelled', array($this, 'on_order_cancelled'));
        add_action('woocommerce_order_status_refunded', array($this, 'on_order_refunded'));
        
        // Abonelik işlemleri (WooCommerce Subscriptions)
        add_action('woocommerce_subscription_status_active', array($this, 'on_subscription_activated'));
        add_action('woocommerce_subscription_status_cancelled', array($this, 'on_subscription_cancelled'));
        add_action('woocommerce_subscription_status_expired', array($this, 'on_subscription_expired'));
        add_action('woocommerce_subscription_status_on-hold', array($this, 'on_subscription_on_hold'));
        
        // Sepet ve checkout
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_course_add_to_cart'), 10, 3);
        add_action('woocommerce_add_to_cart', array($this, 'maybe_empty_cart_before_add'));
        
        // Admin
        add_filter('woocommerce_product_filters', array($this, 'add_course_filter'));
        add_action('restrict_manage_posts', array($this, 'course_admin_filter'));
        add_filter('parse_query', array($this, 'course_admin_filter_query'));
        
        // My Account sayfası
        add_filter('woocommerce_account_menu_items', array($this, 'add_my_courses_menu'));
        add_action('woocommerce_account_my-courses_endpoint', array($this, 'my_courses_content'));
        add_action('init', array($this, 'add_my_courses_endpoint'));
        
        // AJAX işlemleri
        add_action('wp_ajax_add_course_to_cart', array($this, 'ajax_add_course_to_cart'));
        add_action('wp_ajax_nopriv_add_course_to_cart', array($this, 'ajax_add_course_to_cart'));
    }
    
    /**
     * Ürün tiplerini başlat
     */
    private function init_product_types() {
        // Course Product Class
        require_once ASTMED_LMS_PLUGIN_DIR . 'includes/woocommerce/class-wc-product-course.php';
        require_once ASTMED_LMS_PLUGIN_DIR . 'includes/woocommerce/class-wc-product-subscription-course.php';
    }
    
    /**
     * WooCommerce eksik uyarısı
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('ASTMED LMS WooCommerce entegrasyonu için WooCommerce eklentisinin aktif olması gerekiyor.', 'astmed-lms');
        echo '</p></div>';
    }
    
    /**
     * Kurs ürün tiplerini ekle
     */
    public function add_course_product_types($types) {
        $types['course'] = __('Kurs', 'astmed-lms');
        
        // WooCommerce Subscriptions varsa
        if (class_exists('WC_Subscriptions')) {
            $types['course_subscription'] = __('Kurs Aboneliği', 'astmed-lms');
        }
        
        return $types;
    }
    
    /**
     * Kurs ürün sekmesini ekle
     */
    public function add_course_product_tab($tabs) {
        $tabs['astmed_course'] = array(
            'label'    => __('Kurs Ayarları', 'astmed-lms'),
            'target'   => 'astmed_course_product_data',
            'class'    => array('show_if_course', 'show_if_course_subscription'),
            'priority' => 20,
        );
        return $tabs;
    }
    
    /**
     * Kurs ürün sekmesi içeriği
     */
    public function course_product_tab_content() {
        global $post;
        
        $course_id = get_post_meta($post->ID, '_course_id', true);
        $enrollment_duration = get_post_meta($post->ID, '_enrollment_duration', true);
        $access_period = get_post_meta($post->ID, '_access_period', true);
        $drip_content = get_post_meta($post->ID, '_drip_content', true);
        $certificate_included = get_post_meta($post->ID, '_certificate_included', true);
        $instructor_commission = get_post_meta($post->ID, '_instructor_commission', true);
        
        // Kursları al
        $courses = get_posts(array(
            'post_type' => 'astmed_course',
            'numberposts' => -1,
            'post_status' => 'publish'
        ));
        ?>
        
        <div id="astmed_course_product_data" class="panel woocommerce_options_panel">
            
            <div class="options_group">
                <p class="form-field">
                    <label for="_course_id"><?php _e('Bağlı Kurs', 'astmed-lms'); ?></label>
                    <select name="_course_id" id="_course_id" class="wc-enhanced-select">
                        <option value=""><?php _e('Kurs Seçin', 'astmed-lms'); ?></option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo $course->ID; ?>" <?php selected($course_id, $course->ID); ?>>
                                <?php echo esc_html($course->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                
                <p class="form-field">
                    <label for="_enrollment_duration"><?php _e('Kayıt Süresi (Gün)', 'astmed-lms'); ?></label>
                    <input type="number" name="_enrollment_duration" id="_enrollment_duration" 
                           value="<?php echo esc_attr($enrollment_duration); ?>" 
                           placeholder="<?php _e('0 = Sınırsız', 'astmed-lms'); ?>" />
                    <span class="description"><?php _e('Satın alma sonrası kaç gün erişim olacak (0 = sınırsız)', 'astmed-lms'); ?></span>
                </p>
                
                <p class="form-field">
                    <label for="_access_period"><?php _e('Erişim Periyodu', 'astmed-lms'); ?></label>
                    <select name="_access_period" id="_access_period">
                        <option value="lifetime" <?php selected($access_period, 'lifetime'); ?>><?php _e('Yaşam Boyu', 'astmed-lms'); ?></option>
                        <option value="1_year" <?php selected($access_period, '1_year'); ?>><?php _e('1 Yıl', 'astmed-lms'); ?></option>
                        <option value="6_months" <?php selected($access_period, '6_months'); ?>><?php _e('6 Ay', 'astmed-lms'); ?></option>
                        <option value="3_months" <?php selected($access_period, '3_months'); ?>><?php _e('3 Ay', 'astmed-lms'); ?></option>
                        <option value="1_month" <?php selected($access_period, '1_month'); ?>><?php _e('1 Ay', 'astmed-lms'); ?></option>
                    </select>
                </p>
            </div>
            
            <div class="options_group">
                <p class="form-field">
                    <label for="_drip_content">
                        <input type="checkbox" name="_drip_content" id="_drip_content" value="yes" <?php checked($drip_content, 'yes'); ?> />
                        <?php _e('İçerik Kademeli Açılsın', 'astmed-lms'); ?>
                    </label>
                    <span class="description"><?php _e('Dersler belirli aralıklarla açılacak', 'astmed-lms'); ?></span>
                </p>
                
                <p class="form-field">
                    <label for="_certificate_included">
                        <input type="checkbox" name="_certificate_included" id="_certificate_included" value="yes" <?php checked($certificate_included, 'yes'); ?> />
                        <?php _e('Sertifika Dahil', 'astmed-lms'); ?>
                    </label>
                    <span class="description"><?php _e('Kurs tamamlandığında sertifika verilsin', 'astmed-lms'); ?></span>
                </p>
            </div>
            
            <div class="options_group">
                <p class="form-field">
                    <label for="_instructor_commission"><?php _e('Eğitmen Komisyonu (%)', 'astmed-lms'); ?></label>
                    <input type="number" name="_instructor_commission" id="_instructor_commission" 
                           value="<?php echo esc_attr($instructor_commission ?: 50); ?>" 
                           min="0" max="100" step="0.01" />
                    <span class="description"><?php _e('Eğitmenin alacağı komisyon yüzdesi', 'astmed-lms'); ?></span>
                </p>
            </div>
            
        </div>
        
        <?php
    }
    
    /**
     * Kurs ürün verilerini kaydet
     */
    public function save_course_product_data($post_id) {
        $fields = array(
            '_course_id',
            '_enrollment_duration',
            '_access_period',
            '_instructor_commission'
        );
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Checkbox alanları
        $checkbox_fields = array('_drip_content', '_certificate_included');
        foreach ($checkbox_fields as $field) {
            update_post_meta($post_id, $field, isset($_POST[str_replace('_', '', $field)]) ? 'yes' : 'no');
        }
    }
    
    /**
     * Sipariş tamamlandığında
     */
    public function on_order_completed($order_id) {
        $this->process_course_enrollment($order_id, 'completed');
    }
    
    /**
     * Sipariş işleme alındığında
     */
    public function on_order_processing($order_id) {
        $this->process_course_enrollment($order_id, 'processing');
    }
    
    /**
     * Kurs kayıt işlemini yap
     */
    private function process_course_enrollment($order_id, $status) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $user_id = $order->get_user_id();
        if (!$user_id) return;
        
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if (!$product || !in_array($product->get_type(), array('course', 'course_subscription'))) {
                continue;
            }
            
            $course_id = get_post_meta($product_id, '_course_id', true);
            if (!$course_id) continue;
            
            // Önceden kayıtlı mı kontrol et
            if ($this->is_user_enrolled($user_id, $course_id)) {
                continue;
            }
            
            // Erişim süresini hesapla
            $access_period = get_post_meta($product_id, '_access_period', true);
            $expiry_date = $this->calculate_expiry_date($access_period);
            
            // Kayıt yap
            $enrollment_data = array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'product_id' => $product_id,
                'order_id' => $order_id,
                'enrollment_type' => 'purchase',
                'status' => 'active',
                'enrolled_at' => current_time('mysql'),
                'expires_at' => $expiry_date
            );
            
            $this->create_enrollment($enrollment_data);
            
            // Kullanıcıya student rolü ver
            $user = new WP_User($user_id);
            if (!in_array('astmed_student', $user->roles)) {
                $user->add_role('astmed_student');
            }
            
            // Eğitmene komisyon hesapla
            $this->calculate_instructor_commission($product_id, $item->get_total(), $order_id);
            
            // Email bildirim gönder
            $this->send_enrollment_email($user_id, $course_id, $product_id);
            
            // Hook tetikle
            do_action('astmed_course_enrollment_completed', $user_id, $course_id, $order_id);
        }
    }
    
    /**
     * Kullanıcı kursa kayıtlı mı?
     */
    private function is_user_enrolled($user_id, $course_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}astmed_enrollments 
             WHERE user_id = %d AND course_id = %d AND status = 'active'",
            $user_id, $course_id
        ));
        
        return $result > 0;
    }
    
    /**
     * Son kullanma tarihini hesapla
     */
    private function calculate_expiry_date($access_period) {
        if ($access_period === 'lifetime') {
            return null;
        }
        
        $periods = array(
            '1_month' => '+1 month',
            '3_months' => '+3 months',
            '6_months' => '+6 months',
            '1_year' => '+1 year'
        );
        
        if (isset($periods[$access_period])) {
            return date('Y-m-d H:i:s', strtotime($periods[$access_period]));
        }
        
        return null;
    }
    
    /**
     * Kayıt oluştur
     */
    private function create_enrollment($data) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'astmed_enrollments',
            $data,
            array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Eğitmen komisyonu hesapla
     */
    private function calculate_instructor_commission($product_id, $amount, $order_id) {
        $course_id = get_post_meta($product_id, '_course_id', true);
        $instructor_id = get_post_meta($course_id, '_course_instructor', true);
        $commission_rate = get_post_meta($product_id, '_instructor_commission', true) ?: 50;
        
        if (!$instructor_id) return;
        
        $commission_amount = ($amount * $commission_rate) / 100;
        
        // Komisyon kaydı
        $commission_data = array(
            'instructor_id' => $instructor_id,
            'order_id' => $order_id,
            'product_id' => $product_id,
            'course_id' => $course_id,
            'amount' => $amount,
            'commission_rate' => $commission_rate,
            'commission_amount' => $commission_amount,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'astmed_instructor_commissions',
            $commission_data,
            array('%d', '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s')
        );
        
        // Hook tetikle
        do_action('astmed_instructor_commission_created', $instructor_id, $commission_amount, $order_id);
    }
    
    /**
     * Kayıt email'i gönder
     */
    private function send_enrollment_email($user_id, $course_id, $product_id) {
        $user = get_userdata($user_id);
        $course = get_post($course_id);
        $product = wc_get_product($product_id);
        
        $subject = sprintf(__('%s Kursuna Başarıyla Kayıt Oldunuz!', 'astmed-lms'), $course->post_title);
        
        $message = sprintf(
            __('Merhaba %s,<br><br>%s kursuna başarıyla kayıt oldunuz.<br><br>Kursa erişmek için hesabınıza giriş yapabilirsiniz.<br><br>İyi öğrenmeler!', 'astmed-lms'),
            $user->display_name,
            $course->post_title
        );
        
        wp_mail($user->user_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Sipariş iptal edildiğinde
     */
    public function on_order_cancelled($order_id) {
        $this->revoke_course_access($order_id);
    }
    
    /**
     * Sipariş iade edildiğinde
     */
    public function on_order_refunded($order_id) {
        $this->revoke_course_access($order_id);
    }
    
    /**
     * Kurs erişimini iptal et
     */
    private function revoke_course_access($order_id) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'astmed_enrollments',
            array('status' => 'cancelled'),
            array('order_id' => $order_id),
            array('%s'),
            array('%d')
        );
        
        do_action('astmed_course_access_revoked', $order_id);
    }
    
    /**
     * Abonelik aktifleştiğinde
     */
    public function on_subscription_activated($subscription) {
        $this->process_subscription_enrollment($subscription, 'active');
    }
    
    /**
     * Abonelik iptal edildiğinde
     */
    public function on_subscription_cancelled($subscription) {
        $this->process_subscription_enrollment($subscription, 'cancelled');
    }
    
    /**
     * Abonelik süresince dolduğunda
     */
    public function on_subscription_expired($subscription) {
        $this->process_subscription_enrollment($subscription, 'expired');
    }
    
    /**
     * Abonelik askıya alındığında
     */
    public function on_subscription_on_hold($subscription) {
        $this->process_subscription_enrollment($subscription, 'suspended');
    }
    
    /**
     * Abonelik kayıt işlemi
     */
    private function process_subscription_enrollment($subscription, $status) {
        if (!class_exists('WC_Subscription')) return;
        
        $user_id = $subscription->get_user_id();
        if (!$user_id) return;
        
        foreach ($subscription->get_items() as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if (!$product || $product->get_type() !== 'course_subscription') {
                continue;
            }
            
            $course_id = get_post_meta($product_id, '_course_id', true);
            if (!$course_id) continue;
            
            if ($status === 'active') {
                // Abonelik aktif - erişim ver
                $enrollment_data = array(
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'product_id' => $product_id,
                    'subscription_id' => $subscription->get_id(),
                    'enrollment_type' => 'subscription',
                    'status' => 'active',
                    'enrolled_at' => current_time('mysql'),
                    'expires_at' => $subscription->get_date('next_payment') ? $subscription->get_date('next_payment') : null
                );
                
                $this->create_enrollment($enrollment_data);
                
                // Student rolü ver
                $user = new WP_User($user_id);
                if (!in_array('astmed_student', $user->roles)) {
                    $user->add_role('astmed_student');
                }
                
            } else {
                // Abonelik pasif - erişimi iptal et
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'astmed_enrollments',
                    array('status' => $status),
                    array(
                        'user_id' => $user_id,
                        'course_id' => $course_id,
                        'subscription_id' => $subscription->get_id()
                    ),
                    array('%s'),
                    array('%d', '%d', '%d')
                );
            }
        }
    }
    
    /**
     * Sepete eklemeden önce validasyon
     */
    public function validate_course_add_to_cart($passed, $product_id, $quantity) {
        $product = wc_get_product($product_id);
        
        if (!$product || !in_array($product->get_type(), array('course', 'course_subscription'))) {
            return $passed;
        }
        
        // Giriş yapılmış mı?
        if (!is_user_logged_in()) {
            wc_add_notice(__('Kursu satın almak için giriş yapmalısınız.', 'astmed-lms'), 'error');
            return false;
        }
        
        $course_id = get_post_meta($product_id, '_course_id', true);
        $user_id = get_current_user_id();
        
        // Zaten kayıtlı mı?
        if ($this->is_user_enrolled($user_id, $course_id)) {
            wc_add_notice(__('Bu kursa zaten kayıtlısınız.', 'astmed-lms'), 'error');
            return false;
        }
        
        return $passed;
    }
    
    /**
     * Kurs ürünü eklenirken sepeti temizle
     */
    public function maybe_empty_cart_before_add($cart_item_key) {
        // Sadece kurs ürünleri için sepeti temizle
        $cart_item = WC()->cart->get_cart_item($cart_item_key);
        $product = wc_get_product($cart_item['product_id']);
        
        if ($product && in_array($product->get_type(), array('course', 'course_subscription'))) {
            // Sepetteki diğer öğeleri kaldır
            foreach (WC()->cart->get_cart() as $key => $item) {
                if ($key !== $cart_item_key) {
                    WC()->cart->remove_cart_item($key);
                }
            }
            
            wc_add_notice(__('Sepetinizdeki diğer ürünler kaldırıldı. Aynı anda sadece bir kurs satın alabilirsiniz.', 'astmed-lms'), 'notice');
        }
    }
    
    /**
     * Admin kurs filtresi ekle
     */
    public function add_course_filter($filters) {
        $filters['course_products'] = __('Kurs Ürünleri', 'astmed-lms');
        return $filters;
    }
    
    /**
     * Admin filtreleme
     */
    public function course_admin_filter() {
        global $typenow;
        
        if ($typenow === 'product') {
            $selected = isset($_GET['course_filter']) ? $_GET['course_filter'] : '';
            ?>
            <select name="course_filter">
                <option value=""><?php _e('Tüm Ürünler', 'astmed-lms'); ?></option>
                <option value="course" <?php selected($selected, 'course'); ?>><?php _e('Kurs Ürünleri', 'astmed-lms'); ?></option>
                <option value="course_subscription" <?php selected($selected, 'course_subscription'); ?>><?php _e('Kurs Abonelikleri', 'astmed-lms'); ?></option>
            </select>
            <?php
        }
    }
    
    /**
     * Admin filtre sorgusu
     */
    public function course_admin_filter_query($query) {
        global $pagenow, $typenow;
        
        if ($pagenow === 'edit.php' && $typenow === 'product' && isset($_GET['course_filter']) && $_GET['course_filter']) {
            $query->query_vars['meta_query'] = array(
                array(
                    'key' => '_course_id',
                    'compare' => 'EXISTS'
                )
            );
        }
    }
    
    /**
     * My Account'a kurslarım sekmesi ekle
     */
    public function add_my_courses_menu($items) {
        // Orders'dan önce ekle
        $new_items = array();
        foreach ($items as $key => $item) {
            if ($key === 'orders') {
                $new_items['my-courses'] = __('Kurslarım', 'astmed-lms');
            }
            $new_items[$key] = $item;
        }
        
        return $new_items;
    }
    
    /**
     * My Account endpoint ekle
     */
    public function add_my_courses_endpoint() {
        add_rewrite_endpoint('my-courses', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Kurslarım sayfa içeriği
     */
    public function my_courses_content() {
        $user_id = get_current_user_id();
        $enrollments = $this->get_user_enrollments($user_id);
        
        if (empty($enrollments)) {
            echo '<p>' . __('Henüz hiçbir kursa kayıtlı değilsiniz.', 'astmed-lms') . '</p>';
            echo '<p><a href="' . esc_url(wc_get_page_permalink('shop')) . '" class="button">' . __('Kursları Keşfet', 'astmed-lms') . '</a></p>';
            return;
        }
        
        echo '<div class="astmed-my-courses">';
        foreach ($enrollments as $enrollment) {
            $this->render_course_card($enrollment);
        }
        echo '</div>';
    }
    
    /**
     * Kullanıcı kayıtlarını al
     */
    private function get_user_enrollments($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, c.post_title as course_title, c.post_excerpt as course_excerpt
             FROM {$wpdb->prefix}astmed_enrollments e
             LEFT JOIN {$wpdb->posts} c ON e.course_id = c.ID
             WHERE e.user_id = %d AND e.status = 'active'
             ORDER BY e.enrolled_at DESC",
            $user_id
        ));
    }
    
    /**
     * Kurs kartını render et
     */
    private function render_course_card($enrollment) {
        $course = get_post($enrollment->course_id);
        $progress = $this->get_course_progress($enrollment->user_id, $enrollment->course_id);
        
        ?>
        <div class="astmed-course-card">
            <div class="course-thumbnail">
                <?php echo get_the_post_thumbnail($course->ID, 'medium'); ?>
            </div>
            <div class="course-content">
                <h3><?php echo esc_html($course->post_title); ?></h3>
                <p><?php echo esc_html($course->post_excerpt); ?></p>
                
                <div class="course-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                    <span class="progress-text"><?php printf(__('%d%% Tamamlandı', 'astmed-lms'), $progress); ?></span>
                </div>
                
                <div class="course-meta">
                    <span class="enrolled-date">
                        <?php printf(__('Kayıt: %s', 'astmed-lms'), date('d.m.Y', strtotime($enrollment->enrolled_at))); ?>
                    </span>
                    
                    <?php if ($enrollment->expires_at && $enrollment->expires_at !== '0000-00-00 00:00:00') : ?>
                        <span class="expiry-date">
                            <?php printf(__('Bitiş: %s', 'astmed-lms'), date('d.m.Y', strtotime($enrollment->expires_at))); ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="course-actions">
                    <a href="<?php echo get_permalink($course->ID); ?>" class="button">
                        <?php _e('Kursa Git', 'astmed-lms'); ?>
                    </a>
                    
                    <?php if ($progress >= 100) : ?>
                        <a href="<?php echo esc_url(add_query_arg('download_certificate', $course->ID, wc_get_account_endpoint_url('my-courses'))); ?>" 
                           class="button certificate-download">
                            <?php _e('Sertifika İndir', 'astmed-lms'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Kurs ilerlemesini al
     */
    private function get_course_progress($user_id, $course_id) {
        global $wpdb;
        
        $total_lessons = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_type = 'astmed_lesson' 
             AND post_status = 'publish'
             AND ID IN (
                 SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_lesson_course' AND meta_value = %d
             )",
            $course_id
        ));
        
        if (!$total_lessons) return 0;
        
        $completed_lessons = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}astmed_progress 
             WHERE user_id = %d AND course_id = %d AND status = 'completed'",
            $user_id, $course_id
        ));
        
        return round(($completed_lessons / $total_lessons) * 100);
    }
    
    /**
     * AJAX: Kursu sepete ekle
     */
    public function ajax_add_course_to_cart() {
        check_ajax_referer('astmed_lms_nonce', 'nonce');
        
        $course_id = intval($_POST['course_id']);
        
        // Kursa bağlı ürünü bul
        $product_id = $this->get_course_product_id($course_id);
        
        if (!$product_id) {
            wp_send_json_error(__('Bu kurs için ürün bulunamadı.', 'astmed-lms'));
        }
        
        // Sepete ekle
        $cart_item_key = WC()->cart->add_to_cart($product_id);
        
        if ($cart_item_key) {
            wp_send_json_success(array(
                'message' => __('Kurs sepete eklendi.', 'astmed-lms'),
                'cart_url' => wc_get_cart_url()
            ));
        } else {
            wp_send_json_error(__('Kurs sepete eklenemedi.', 'astmed-lms'));
        }
    }
    
    /**
     * Kursa bağlı ürün ID'sini bul
     */
    private function get_course_product_id($course_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_course_id' AND meta_value = %d
             AND post_id IN (
                 SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = 'product' AND post_status = 'publish'
             )
             LIMIT 1",
            $course_id
        ));
    }
    
    /**
     * Kurs satın alma butonu shortcode
     */
    public function course_purchase_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'course_id' => 0,
            'text' => __('Kursu Satın Al', 'astmed-lms'),
            'class' => 'astmed-lms-button astmed-lms-button-primary'
        ), $atts);
        
        if (!$atts['course_id']) return '';
        
        $course_id = intval($atts['course_id']);
        $product_id = $this->get_course_product_id($course_id);
        
        if (!$product_id) return '';
        
        $product = wc_get_product($product_id);
        if (!$product) return '';
        
        // Kullanıcı kontrolü
        if (!is_user_logged_in()) {
            return sprintf(
                '<a href="%s" class="%s">%s</a>',
                esc_url(wc_get_page_permalink('myaccount')),
                esc_attr($atts['class']),
                __('Giriş Yap', 'astmed-lms')
            );
        }
        
        // Zaten kayıtlı mı?
        if ($this->is_user_enrolled(get_current_user_id(), $course_id)) {
            return sprintf(
                '<a href="%s" class="%s astmed-lms-button-success">%s</a>',
                esc_url(get_permalink($course_id)),
                esc_attr($atts['class']),
                __('Kursa Git', 'astmed-lms')
            );
        }
        
        // Fiyat bilgisi
        $price_html = $product->get_price_html();
        
        return sprintf(
            '<div class="astmed-course-purchase">
                <div class="course-price">%s</div>
                <button class="astmed-add-to-cart %s" data-course-id="%d" data-product-id="%d">
                    %s
                </button>
            </div>',
            $price_html,
            esc_attr($atts['class']),
            $course_id,
            $product_id,
            esc_html($atts['text'])
        );
    }
    
    /**
     * WooCommerce checkout'ta kurs bilgilerini göster
     */
    public function add_course_info_to_checkout() {
        add_action('woocommerce_checkout_before_customer_details', array($this, 'display_course_checkout_info'));
    }
    
    /**
     * Checkout'ta kurs bilgilerini göster
     */
    public function display_course_checkout_info() {
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            
            if (!in_array($product->get_type(), array('course', 'course_subscription'))) {
                continue;
            }
            
            $course_id = get_post_meta($product->get_id(), '_course_id', true);
            if (!$course_id) continue;
            
            $course = get_post($course_id);
            $instructor_id = get_post_meta($course_id, '_course_instructor', true);
            $instructor = $instructor_id ? get_userdata($instructor_id) : null;
            $duration = get_post_meta($course_id, '_course_duration', true);
            
            ?>
            <div class="astmed-checkout-course-info">
                <h3><?php _e('Kurs Detayları', 'astmed-lms'); ?></h3>
                <div class="course-details">
                    <?php if (has_post_thumbnail($course_id)) : ?>
                        <div class="course-thumbnail">
                            <?php echo get_the_post_thumbnail($course_id, 'thumbnail'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="course-info">
                        <h4><?php echo esc_html($course->post_title); ?></h4>
                        <p><?php echo esc_html($course->post_excerpt); ?></p>
                        
                        <?php if ($instructor) : ?>
                            <p class="instructor">
                                <strong><?php _e('Eğitmen:', 'astmed-lms'); ?></strong> 
                                <?php echo esc_html($instructor->display_name); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($duration) : ?>
                            <p class="duration">
                                <strong><?php _e('Süre:', 'astmed-lms'); ?></strong> 
                                <?php printf(__('%s saat', 'astmed-lms'), $duration); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="access-info">
                            <?php
                            $access_period = get_post_meta($product->get_id(), '_access_period', true);
                            if ($access_period === 'lifetime') {
                                echo '<span class="lifetime-access">' . __('Yaşam Boyu Erişim', 'astmed-lms') . '</span>';
                            } else {
                                $periods = array(
                                    '1_month' => __('1 Ay Erişim', 'astmed-lms'),
                                    '3_months' => __('3 Ay Erişim', 'astmed-lms'),
                                    '6_months' => __('6 Ay Erişim', 'astmed-lms'),
                                    '1_year' => __('1 Yıl Erişim', 'astmed-lms')
                                );
                                echo '<span class="limited-access">' . ($periods[$access_period] ?? __('Sınırlı Erişim', 'astmed-lms')) . '</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Kurs ürünlerinde stok yönetimini devre dışı bırak
     */
    public function disable_stock_management_for_courses($enabled, $product) {
        if (in_array($product->get_type(), array('course', 'course_subscription'))) {
            return false;
        }
        return $enabled;
    }
    
    /**
     * Kurs ürünlerinde teslimat seçeneklerini gizle
     */
    public function hide_shipping_for_courses($rates, $package) {
        $has_course = false;
        
        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            if (in_array($product->get_type(), array('course', 'course_subscription'))) {
                $has_course = true;
                break;
            }
        }
        
        if ($has_course) {
            return array(); // Teslimat seçeneklerini gizle
        }
        
        return $rates;
    }
    
    /**
     * Email bildirimlerini özelleştir
     */
    public function customize_course_emails() {
        // Kurs satın alma sonrası özel email template
        add_action('woocommerce_email_before_order_table', array($this, 'add_course_info_to_email'), 10, 4);
    }
    
    /**
     * Email'lere kurs bilgilerini ekle
     */
    public function add_course_info_to_email($order, $sent_to_admin, $plain_text, $email) {
        if ($email->id !== 'customer_completed_order') return;
        
        $course_items = array();
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            
            if (in_array($product->get_type(), array('course', 'course_subscription'))) {
                $course_id = get_post_meta($product->get_id(), '_course_id', true);
                if ($course_id) {
                    $course_items[] = array(
                        'course' => get_post($course_id),
                        'product' => $product,
                        'url' => get_permalink($course_id)
                    );
                }
            }
        }
        
        if (!empty($course_items)) {
            echo '<h2>' . __('Kurslarınıza Erişim', 'astmed-lms') . '</h2>';
            echo '<p>' . __('Aşağıdaki kurslara artık erişiminiz bulunmaktadır:', 'astmed-lms') . '</p>';
            
            foreach ($course_items as $item) {
                printf(
                    '<p><strong>%s</strong><br><a href="%s">%s</a></p>',
                    esc_html($item['course']->post_title),
                    esc_url($item['url']),
                    __('Kursa Git', 'astmed-lms')
                );
            }
        }
    }
    
    /**
     * Kurs ürünlerinde kategori filtresi
     */
    public function add_course_category_filter() {
        add_action('woocommerce_product_query', array($this, 'filter_products_by_course_category'));
    }
    
    /**
     * Ürün sorgusunu kurs kategorisine göre filtrele
     */
    public function filter_products_by_course_category($query) {
        if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category())) {
            if (isset($_GET['course_category']) && $_GET['course_category']) {
                $course_category = sanitize_text_field($_GET['course_category']);
                
                // Kurs ürünlerini filtrele
                $meta_query = $query->get('meta_query') ?: array();
                $meta_query[] = array(
                    'key' => '_course_id',
                    'compare' => 'EXISTS'
                );
                
                $query->set('meta_query', $meta_query);
                
                // Kurs kategorisine göre filtrele
                $tax_query = $query->get('tax_query') ?: array();
                $tax_query[] = array(
                    'taxonomy' => 'course_category',
                    'field' => 'slug',
                    'terms' => $course_category
                );
                
                $query->set('tax_query', $tax_query);
            }
        }
    }
    
    /**
     * Rapor widget'ları ekle
     */
    public function add_course_sales_reports() {
        if (current_user_can('manage_woocommerce')) {
            add_action('woocommerce_admin_dashboard', array($this, 'course_sales_widget'));
        }
    }
    
    /**
     * Kurs satış widget'ı
     */
    public function course_sales_widget() {
        $course_sales = $this->get_course_sales_data();
        
        ?>
        <div class="astmed-course-sales-widget">
            <h3><?php _e('Kurs Satışları', 'astmed-lms'); ?></h3>
            <div class="sales-stats">
                <div class="stat-item">
                    <span class="number"><?php echo $course_sales['total_sales']; ?></span>
                    <span class="label"><?php _e('Toplam Satış', 'astmed-lms'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="number"><?php echo wc_price($course_sales['total_revenue']); ?></span>
                    <span class="label"><?php _e('Toplam Gelir', 'astmed-lms'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="number"><?php echo $course_sales['active_students']; ?></span>
                    <span class="label"><?php _e('Aktif Öğrenci', 'astmed-lms'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Kurs satış verilerini al
     */
    private function get_course_sales_data() {
        global $wpdb;
        
        // Son 30 günlük veriler
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
        
        $total_sales = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}astmed_enrollments 
             WHERE enrollment_type = 'purchase' 
             AND enrolled_at BETWEEN %s AND %s",
            $start_date, $end_date
        ));
        
        $total_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total) FROM {$wpdb->prefix}wc_order_stats 
             WHERE status IN ('wc-completed', 'wc-processing')
             AND date_created BETWEEN %s AND %s",
            $start_date, $end_date
        ));
        
        $active_students = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}astmed_enrollments 
             WHERE status = 'active'"
        );
        
        return array(
            'total_sales' => intval($total_sales),
            'total_revenue' => floatval($total_revenue),
            'active_students' => intval($active_students)
        );
    }
}

// Shortcode'ları kaydet
add_shortcode('astmed_course_purchase_button', array('ASTMED_LMS_WooCommerce_Integration', 'course_purchase_button_shortcode'));

// Hook'ları başlat
add_action('init', function() {
    if (class_exists('ASTMED_LMS_WooCommerce_Integration')) {
        new ASTMED_LMS_WooCommerce_Integration();
    }
});