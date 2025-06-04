<?php
/**
 * WooCommerce Course Subscription Product Class
 * Kurs abonelikleri için özel WooCommerce ürün sınıfı
 */

if (!defined('ABSPATH')) exit;

// WooCommerce Subscriptions kontrolü
if (!class_exists('WC_Product_Subscription')) {
    return;
}

class WC_Product_Course_Subscription extends WC_Product_Subscription {
    
    /**
     * Constructor
     */
    public function __construct($product = 0) {
        $this->product_type = 'course_subscription';
        parent::__construct($product);
    }
    
    /**
     * Get type
     */
    public function get_type() {
        return 'course_subscription';
    }
    
    /**
     * Abonelik ürünleri dijital
     */
    public function is_virtual() {
        return true;
    }
    
    /**
     * İndirilebilir değil
     */
    public function is_downloadable() {
        return false;
    }
    
    /**
     * Stok yönetimi yok
     */
    public function managing_stock() {
        return false;
    }
    
    /**
     * Teslimat gerekmiyor
     */
    public function needs_shipping() {
        return false;
    }
    
    /**
     * Abonelik planına dahil kursları al
     */
    public function get_included_courses() {
        $included_courses = get_post_meta($this->get_id(), '_included_courses', true);
        return is_array($included_courses) ? $included_courses : array();
    }
    
    /**
     * Abonelik türünü al (all_courses, specific_courses, category_based)
     */
    public function get_subscription_type() {
        return get_post_meta($this->get_id(), '_subscription_type', true) ?: 'all_courses';
    }
    
    /**
     * Dahil edilen kategorileri al
     */
    public function get_included_categories() {
        $included_categories = get_post_meta($this->get_id(), '_included_categories', true);
        return is_array($included_categories) ? $included_categories : array();
    }
    
    /**
     * Maksimum eş zamanlı kurs limiti
     */
    public function get_concurrent_course_limit() {
        return get_post_meta($this->get_id(), '_concurrent_course_limit', true) ?: 0; // 0 = sınırsız
    }
    
    /**
     * İndirimli kurs fiyatları (% indirim)
     */
    public function get_course_discount_percentage() {
        return get_post_meta($this->get_id(), '_course_discount_percentage', true) ?: 0;
    }
    
    /**
     * Deneme süresi (gün)
     */
    public function get_trial_period() {
        return get_post_meta($this->get_id(), '_trial_period', true) ?: 0;
    }
    
    /**
     * Erken erişim (yeni kurslar kaç gün önce erişilebilir)
     */
    public function get_early_access_days() {
        return get_post_meta($this->get_id(), '_early_access_days', true) ?: 0;
    }
    
    /**
     * Premium özellikler dahil mi?
     */
    public function includes_premium_features() {
        return get_post_meta($this->get_id(), '_premium_features', true) === 'yes';
    }
    
    /**
     * 1:1 danışmanlık dahil mi?
     */
    public function includes_mentoring() {
        return get_post_meta($this->get_id(), '_includes_mentoring', true) === 'yes';
    }
    
    /**
     * Aylık danışmanlık süresi (dakika)
     */
    public function get_monthly_mentoring_minutes() {
        return get_post_meta($this->get_id(), '_monthly_mentoring_minutes', true) ?: 0;
    }
    
    /**
     * Satın alınabilir mi kontrol et
     */
    public function is_purchasable() {
        if (!parent::is_purchasable()) {
            return false;
        }
        
        // Kullanıcının aktif aboneliği var mı?
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            if ($this->user_has_active_subscription($user_id)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Kullanıcının aktif aboneliği var mı?
     */
    private function user_has_active_subscription($user_id) {
        if (!function_exists('wcs_user_has_subscription')) {
            return false;
        }
        
        return wcs_user_has_subscription($user_id, '', 'active');
    }
    
    /**
     * Aboneliğe dahil tüm kursları al
     */
    public function get_accessible_courses($user_id = null) {
        $subscription_type = $this->get_subscription_type();
        $accessible_courses = array();
        
        switch ($subscription_type) {
            case 'all_courses':
                // Tüm kurslar dahil
                $accessible_courses = get_posts(array(
                    'post_type' => 'astmed_course',
                    'post_status' => 'publish',
                    'numberposts' => -1,
                    'fields' => 'ids'
                ));
                break;
                
            case 'specific_courses':
                // Belirli kurslar
                $accessible_courses = $this->get_included_courses();
                break;
                
            case 'category_based':
                // Kategori bazlı
                $categories = $this->get_included_categories();
                if (!empty($categories)) {
                    $accessible_courses = get_posts(array(
                        'post_type' => 'astmed_course',
                        'post_status' => 'publish',
                        'numberposts' => -1,
                        'fields' => 'ids',
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'course_category',
                                'field' => 'term_id',
                                'terms' => $categories
                            )
                        )
                    ));
                }
                break;
        }
        
        // Erken erişim kontrolü
        $early_access_days = $this->get_early_access_days();
        if ($early_access_days > 0 && $user_id) {
            $early_courses = $this->get_early_access_courses($early_access_days);
            $accessible_courses = array_merge($accessible_courses, $early_courses);
        }
        
        return array_unique($accessible_courses);
    }
    
    /**
     * Erken erişim kurslarını al
     */
    private function get_early_access_courses($days) {
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return get_posts(array(
            'post_type' => 'astmed_course',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
            'date_query' => array(
                array(
                    'after' => $cutoff_date,
                    'inclusive' => true
                )
            )
        ));
    }
    
    /**
     * Kullanıcı bu kursa erişebilir mi?
     */
    public function can_access_course($course_id, $user_id) {
        // Kullanıcının aktif aboneliği var mı?
        if (!$this->user_has_active_subscription($user_id)) {
            return false;
        }
        
        // Kurs dahil mi?
        $accessible_courses = $this->get_accessible_courses($user_id);
        if (!in_array($course_id, $accessible_courses)) {
            return false;
        }
        
        // Eş zamanlı kurs limiti
        $concurrent_limit = $this->get_concurrent_course_limit();
        if ($concurrent_limit > 0) {
            $active_enrollments = $this->get_user_active_enrollments($user_id);
            if (count($active_enrollments) >= $concurrent_limit && !in_array($course_id, $active_enrollments)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Kullanıcının aktif kayıtlarını al
     */
    private function get_user_active_enrollments($user_id) {
        global $wpdb;
        
        $enrollments = $wpdb->get_col($wpdb->prepare(
            "SELECT course_id FROM {$wpdb->prefix}astmed_enrollments 
             WHERE user_id = %d AND status = 'active' AND enrollment_type = 'subscription'",
            $user_id
        ));
        
        return $enrollments ?: array();
    }
    
    /**
     * Abonelik özelliklerini al
     */
    public function get_subscription_features() {
        $features = array();
        
        // Temel özellikler
        $subscription_type = $this->get_subscription_type();
        switch ($subscription_type) {
            case 'all_courses':
                $features[] = __('Tüm kurslara sınırsız erişim', 'astmed-lms');
                break;
            case 'specific_courses':
                $course_count = count($this->get_included_courses());
                $features[] = sprintf(_n('%d kursa erişim', '%d kursa erişim', $course_count, 'astmed-lms'), $course_count);
                break;
            case 'category_based':
                $features[] = __('Seçili kategorilerdeki kurslara erişim', 'astmed-lms');
                break;
        }
        
        // Eş zamanlı kurs limiti
        $concurrent_limit = $this->get_concurrent_course_limit();
        if ($concurrent_limit > 0) {
            $features[] = sprintf(__('Aynı anda maksimum %d kurs', 'astmed-lms'), $concurrent_limit);
        } else {
            $features[] = __('Sınırsız eş zamanlı kurs', 'astmed-lms');
        }
        
        // İndirim
        $discount = $this->get_course_discount_percentage();
        if ($discount > 0) {
            $features[] = sprintf(__('Ek kurs alımlarında %%%d indirim', 'astmed-lms'), $discount);
        }
        
        // Erken erişim
        $early_access = $this->get_early_access_days();
        if ($early_access > 0) {
            $features[] = sprintf(__('Yeni kurslara %d gün erken erişim', 'astmed-lms'), $early_access);
        }
        
        // Premium özellikler
        if ($this->includes_premium_features()) {
            $features[] = __('Premium içerik ve özellikler', 'astmed-lms');
        }
        
        // Danışmanlık
        if ($this->includes_mentoring()) {
            $minutes = $this->get_monthly_mentoring_minutes();
            if ($minutes > 0) {
                $features[] = sprintf(__('Aylık %d dakika 1:1 danışmanlık', 'astmed-lms'), $minutes);
            } else {
                $features[] = __('Sınırsız 1:1 danışmanlık', 'astmed-lms');
            }
        }
        
        // Deneme süresi
        $trial = $this->get_trial_period();
        if ($trial > 0) {
            $features[] = sprintf(__('%d gün ücretsiz deneme', 'astmed-lms'), $trial);
        }
        
        return $features;
    }
    
    /**
     * Abonelik durumu widget'ı
     */
    public function get_subscription_status_widget($user_id) {
        if (!$this->user_has_active_subscription($user_id)) {
            return '';
        }
        
        $subscription = wcs_get_users_subscriptions($user_id);
        if (empty($subscription)) return '';
        
        $subscription = array_shift($subscription);
        $next_payment = $subscription->get_date('next_payment');
        $end_date = $subscription->get_date('end');
        
        ob_start();
        ?>
        <div class="astmed-subscription-widget">
            <h4><?php _e('Abonelik Durumu', 'astmed-lms'); ?></h4>
            <div class="subscription-info">
                <div class="status">
                    <span class="label"><?php _e('Durum:', 'astmed-lms'); ?></span>
                    <span class="value status-<?php echo esc_attr($subscription->get_status()); ?>">
                        <?php echo esc_html(wcs_get_subscription_status_name($subscription->get_status())); ?>
                    </span>
                </div>
                
                <?php if ($next_payment) : ?>
                    <div class="next-payment">
                        <span class="label"><?php _e('Sonraki Ödeme:', 'astmed-lms'); ?></span>
                        <span class="value"><?php echo date('d.m.Y', strtotime($next_payment)); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($end_date) : ?>
                    <div class="end-date">
                        <span class="label"><?php _e('Bitiş Tarihi:', 'astmed-lms'); ?></span>
                        <span class="value"><?php echo date('d.m.Y', strtotime($end_date)); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="accessible-courses">
                    <span class="label"><?php _e('Erişilebilir Kurslar:', 'astmed-lms'); ?></span>
                    <span class="value"><?php echo count($this->get_accessible_courses($user_id)); ?></span>
                </div>
                
                <?php
                $concurrent_limit = $this->get_concurrent_course_limit();
                if ($concurrent_limit > 0) :
                    $active_count = count($this->get_user_active_enrollments($user_id));
                ?>
                    <div class="active-courses">
                        <span class="label"><?php _e('Aktif Kurslar:', 'astmed-lms'); ?></span>
                        <span class="value"><?php echo $active_count . '/' . $concurrent_limit; ?></span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="subscription-actions">
                <a href="<?php echo esc_url($subscription->get_view_order_url()); ?>" class="button">
                    <?php _e('Aboneliği Yönet', 'astmed-lms'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Abonelik fiyat hesaplama
     */
    public function get_subscription_price_html() {
        $price_html = parent::get_price_html();
        
        // Deneme süresi varsa ekle
        $trial = $this->get_trial_period();
        if ($trial > 0) {
            $trial_text = sprintf(__('%d gün ücretsiz, sonra ', 'astmed-lms'), $trial);
            $price_html = $trial_text . $price_html;
        }
        
        return $price_html;
    }
    
    /**
     * Abonelik aktivasyon hook'u
     */
    public function on_subscription_activated($subscription) {
        $user_id = $subscription->get_user_id();
        $accessible_courses = $this->get_accessible_courses($user_id);
        
        // Tüm erişilebilir kursları kaydet
        foreach ($accessible_courses as $course_id) {
            $this->create_subscription_enrollment($user_id, $course_id, $subscription->get_id());
        }
        
        // Premium rol ekle
        $user = new WP_User($user_id);
        $user->add_role('astmed_premium_student');
        
        do_action('astmed_subscription_activated', $user_id, $this->get_id(), $subscription->get_id());
    }
    
    /**
     * Abonelik kayıt oluştur
     */
    private function create_subscription_enrollment($user_id, $course_id, $subscription_id) {
        global $wpdb;
        
        // Zaten kayıtlı mı?
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}astmed_enrollments 
             WHERE user_id = %d AND course_id = %d AND status = 'active'",
            $user_id, $course_id
        ));
        
        if ($existing) return;
        
        $wpdb->insert(
            $wpdb->prefix . 'astmed_enrollments',
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'enrollment_type' => 'subscription',
                'subscription_id' => $subscription_id,
                'status' => 'active',
                'enrolled_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%d', '%s', '%s')
        );
    }
    
    /**
     * Abonelik iptal edildiğinde
     */
    public function on_subscription_cancelled($subscription) {
        $user_id = $subscription->get_user_id();
        
        // Abonelik kayıtlarını iptal et
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'astmed_enrollments',
            array('status' => 'cancelled'),
            array(
                'user_id' => $user_id,
                'subscription_id' => $subscription->get_id(),
                'enrollment_type' => 'subscription'
            ),
            array('%s'),
            array('%d', '%d', '%s')
        );
        
        // Premium rolü kaldır
        $user = new WP_User($user_id);
        $user->remove_role('astmed_premium_student');
        
        do_action('astmed_subscription_cancelled', $user_id, $this->get_id(), $subscription->get_id());
    }
    
    /**
     * Ek bilgiler
     */
    public function get_additional_information() {
        $info = parent::get_additional_information();
        
        // Abonelik özelliklerini ekle
        $features = $this->get_subscription_features();
        if (!empty($features)) {
            $info[__('Abonelik Özellikleri', 'astmed-lms')] = implode('<br>', $features);
        }
        
        return $info;
    }
    
    /**
     * Schema markup
     */
    public function get_schema_markup() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'EducationalOccupationalProgram',
            'name' => $this->get_name(),
            'description' => $this->get_description(),
            'provider' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url()
            )
        );
        
        // Abonelik fiyatı
        if ($this->get_price()) {
            $billing_period = $this->get_billing_period();
            $billing_interval = $this->get_billing_interval();
            
            $schema['offers'] = array(
                '@type' => 'Offer',
                'price' => $this->get_price(),
                'priceCurrency' => get_woocommerce_currency(),
                'priceSpecification' => array(
                    '@type' => 'RecurringPaymentFrequency',
                    'billingDuration' => 'P' . $billing_interval . strtoupper(substr($billing_period, 0, 1)),
                    'billingIncrement' => $billing_interval
                )
            );
            
            // Deneme süresi
            $trial = $this->get_trial_period();
            if ($trial > 0) {
                $schema['offers']['eligibleDuration'] = 'P' . $trial . 'D';
            }
        }
        
        // Dahil edilen kurslar
        $accessible_courses = $this->get_accessible_courses();
        if (!empty($accessible_courses)) {
            $schema['hasCourse'] = array();
            foreach ($accessible_courses as $course_id) {
                $course = get_post($course_id);
                if ($course) {
                    $schema['hasCourse'][] = array(
                        '@type' => 'Course',
                        'name' => $course->post_title,
                        'url' => get_permalink($course_id)
                    );
                }
            }
        }
        
        return '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }
}