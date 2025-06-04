<?php
/**
 * WooCommerce Course Product Class
 * Kurs ürünleri için özel WooCommerce ürün sınıfı
 */

if (!defined('ABSPATH')) exit;

class WC_Product_Course extends WC_Product {
    
    /**
     * Constructor
     */
    public function __construct($product = 0) {
        $this->product_type = 'course';
        parent::__construct($product);
    }
    
    /**
     * Get type
     */
    public function get_type() {
        return 'course';
    }
    
    /**
     * Kurs ürünleri dijital ürün
     */
    public function is_virtual() {
        return true;
    }
    
    /**
     * Kurs ürünleri indirilebilir değil
     */
    public function is_downloadable() {
        return false;
    }
    
    /**
     * Kurs ürünlerinde stok yönetimi yok
     */
    public function managing_stock() {
        return false;
    }
    
    /**
     * Kurs ürünleri her zaman stokta
     */
    public function is_in_stock() {
        return true;
    }
    
    /**
     * Kurs ürünlerinde teslimat yok
     */
    public function needs_shipping() {
        return false;
    }
    
    /**
     * Satın alınabilir mi kontrol et
     */
    public function is_purchasable() {
        // Temel kontroller
        if (!parent::is_purchasable()) {
            return false;
        }
        
        // Kurs bağlı mı?
    /**
     * Kurs öğrenci sayısını al
     */
    public function get_enrolled_count() {
        global $wpdb;
        
        $course_id = $this->get_course_id();
        if (!$course_id) return 0;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}astmed_enrollments 
             WHERE course_id = %d AND status = 'active'",
            $course_id
        ));
    }
    
    /**
     * Kurs dolu mu kontrol et
     */
    public function is_course_full() {
        $course_id = $this->get_course_id();
        if (!$course_id) return false;
        
        $max_students = get_post_meta($course_id, '_course_max_students', true);
        if (!$max_students) return false; // Sınır yoksa dolu değil
        
        $enrolled_count = $this->get_enrolled_count();
        
        return $enrolled_count >= $max_students;
    }
    
    /**
     * Kalan kontenjan
     */
    public function get_remaining_spots() {
        $course_id = $this->get_course_id();
        if (!$course_id) return null;
        
        $max_students = get_post_meta($course_id, '_course_max_students', true);
        if (!$max_students) return null; // Sınırsız
        
        $enrolled_count = $this->get_enrolled_count();
        
        return max(0, $max_students - $enrolled_count);
    }
    
    /**
     * Stok durumu kontrolü (kontenjan için)
     */
    public function get_stock_status() {
        if ($this->is_course_full()) {
            return 'outofstock';
        }
        
        return 'instock';
    }
    
    /**
     * Kurs başlangıç tarihi
     */
    public function get_course_start_date() {
        $course_id = $this->get_course_id();
        if (!$course_id) return null;
        
        return get_post_meta($course_id, '_course_start_date', true);
    }
    
    /**
     * Kurs bitiş tarihi
     */
    public function get_course_end_date() {
        $course_id = $this->get_course_id();
        if (!$course_id) return null;
        
        return get_post_meta($course_id, '_course_end_date', true);
    }
    
    /**
     * Kurs aktif mi?
     */
    public function is_course_active() {
        $start_date = $this->get_course_start_date();
        $end_date = $this->get_course_end_date();
        $now = current_time('timestamp');
        
        if ($start_date && strtotime($start_date) > $now) {
            return false; // Henüz başlamamış
        }
        
        if ($end_date && strtotime($end_date) < $now) {
            return false; // Bitmiş
        }
        
        return true;
    }
    
    /**
     * Ürün meta bilgilerini kaydet
     */
    public function save_meta_data() {
        parent::save_meta_data();
        
        // Kurs ürünü özel ayarları
        if ($this->get_course_id()) {
            // Virtual ve downloadable ayarla
            $this->set_virtual(true);
            $this->set_downloadable(false);
            
            // Stok yönetimini kapat
            $this->set_manage_stock(false);
            $this->set_stock_status('instock');
            
            // Teslimat kapat
            $this->set_virtual(true);
        }
    }
    
    /**
     * Sepete ekleme validasyonu
     */
    public function validate_add_to_cart($quantity = 1) {
        // Temel validasyon
        if (!parent::validate_add_to_cart($quantity)) {
            return false;
        }
        
        // Kurs dolu mu?
        if ($this->is_course_full()) {
            wc_add_notice(__('Bu kurs dolu. Kayıt kabul edilmiyor.', 'astmed-lms'), 'error');
            return false;
        }
        
        // Kurs aktif mi?
        if (!$this->is_course_active()) {
            $start_date = $this->get_course_start_date();
            if ($start_date && strtotime($start_date) > current_time('timestamp')) {
                wc_add_notice(sprintf(__('Bu kurs %s tarihinde başlayacak.', 'astmed-lms'), date('d.m.Y', strtotime($start_date))), 'error');
                return false;
            }
            
            wc_add_notice(__('Bu kursun kayıt süresi sona ermiş.', 'astmed-lms'), 'error');
            return false;
        }
        
        // Kullanıcı giriş yapmış mı?
        if (!is_user_logged_in()) {
            wc_add_notice(__('Kursu satın almak için giriş yapmalısınız.', 'astmed-lms'), 'error');
            return false;
        }
        
        // Zaten kayıtlı mı?
        $user_id = get_current_user_id();
        $course_id = $this->get_course_id();
        
        if ($this->is_user_enrolled($user_id, $course_id)) {
            wc_add_notice(__('Bu kursa zaten kayıtlısınız.', 'astmed-lms'), 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Ürün detay sayfasında ek bilgiler
     */
    public function get_additional_information() {
        $course_info = $this->get_course_info();
        if (!$course_info) return array();
        
        $info = array();
        
        // Eğitmen
        if ($course_info['instructor']) {
            $info[__('Eğitmen', 'astmed-lms')] = $course_info['instructor']->display_name;
        }
        
        // Seviye
        if ($course_info['level']) {
            $levels = array(
                'beginner' => __('Başlangıç', 'astmed-lms'),
                'intermediate' => __('Orta', 'astmed-lms'),
                'advanced' => __('İleri', 'astmed-lms'),
                'expert' => __('Uzman', 'astmed-lms')
            );
            $info[__('Seviye', 'astmed-lms')] = $levels[$course_info['level']] ?? $course_info['level'];
        }
        
        // Süre
        if ($course_info['duration']) {
            $info[__('Toplam Süre', 'astmed-lms')] = sprintf(__('%s saat', 'astmed-lms'), $course_info['duration']);
        }
        
        // Ders sayısı
        if ($course_info['lesson_count']) {
            $info[__('Ders Sayısı', 'astmed-lms')] = $course_info['lesson_count'];
        }
        
        // Quiz sayısı
        if ($course_info['quiz_count']) {
            $info[__('Quiz Sayısı', 'astmed-lms')] = $course_info['quiz_count'];
        }
        
        // Erişim süresi
        $access_period = $this->get_access_period();
        if ($access_period === 'lifetime') {
            $info[__('Erişim Süresi', 'astmed-lms')] = __('Yaşam Boyu', 'astmed-lms');
        } else {
            $periods = array(
                '1_month' => __('1 Ay', 'astmed-lms'),
                '3_months' => __('3 Ay', 'astmed-lms'),
                '6_months' => __('6 Ay', 'astmed-lms'),
                '1_year' => __('1 Yıl', 'astmed-lms')
            );
            $info[__('Erişim Süresi', 'astmed-lms')] = $periods[$access_period] ?? __('Sınırlı', 'astmed-lms');
        }
        
        // Sertifika
        if ($this->includes_certificate()) {
            $info[__('Sertifika', 'astmed-lms')] = __('Dahil', 'astmed-lms');
        }
        
        // Kontenjan
        $remaining_spots = $this->get_remaining_spots();
        if ($remaining_spots !== null) {
            $info[__('Kalan Kontenjan', 'astmed-lms')] = $remaining_spots;
        }
        
        return $info;
    }
    
    /**
     * JSON-LD schema markup
     */
    public function get_schema_markup() {
        $course_info = $this->get_course_info();
        if (!$course_info) return '';
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $this->get_name(),
            'description' => $this->get_description(),
            'provider' => array(
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url()
            )
        );
        
        // Fiyat
        if ($this->get_price()) {
            $schema['offers'] = array(
                '@type' => 'Offer',
                'price' => $this->get_price(),
                'priceCurrency' => get_woocommerce_currency(),
                'availability' => $this->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'
            );
        }
        
        // Eğitmen
        if ($course_info['instructor']) {
            $schema['instructor'] = array(
                '@type' => 'Person',
                'name' => $course_info['instructor']->display_name
            );
        }
        
        // Süre
        if ($course_info['duration']) {
            $schema['timeRequired'] = 'PT' . $course_info['duration'] . 'H';
        }
        
        // Seviye
        if ($course_info['level']) {
            $schema['educationalLevel'] = $course_info['level'];
        }
        
        return '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
    }
} $this->get_course_id();
        if (!$course_id) {
            return false;
        }
        
        // Kurs yayınlanmış mı?
        $course = get_post($course_id);
        if (!$course || $course->post_status !== 'publish') {
            return false;
        }
        
        // Kullanıcı zaten kayıtlı mı?
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            if ($this->is_user_enrolled($user_id, $course_id)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Kursa bağlı ID'yi al
     */
    public function get_course_id() {
        return get_post_meta($this->get_id(), '_course_id', true);
    }
    
    /**
     * Erişim süresini al
     */
    public function get_access_period() {
        return get_post_meta($this->get_id(), '_access_period', true) ?: 'lifetime';
    }
    
    /**
     * Kayıt süresini al
     */
    public function get_enrollment_duration() {
        return get_post_meta($this->get_id(), '_enrollment_duration', true) ?: 0;
    }
    
    /**
     * İçerik kademeli mi açılıyor?
     */
    public function has_drip_content() {
        return get_post_meta($this->get_id(), '_drip_content', true) === 'yes';
    }
    
    /**
     * Sertifika dahil mi?
     */
    public function includes_certificate() {
        return get_post_meta($this->get_id(), '_certificate_included', true) === 'yes';
    }
    
    /**
     * Eğitmen komisyon oranını al
     */
    public function get_instructor_commission() {
        return get_post_meta($this->get_id(), '_instructor_commission', true) ?: 50;
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
     * Sepete ekleme butonunu özelleştir
     */
    public function add_to_cart_button_text() {
        if (!is_user_logged_in()) {
            return __('Giriş Yapın', 'astmed-lms');
        }
        
        $course_id = $this->get_course_id();
        if ($course_id && $this->is_user_enrolled(get_current_user_id(), $course_id)) {
            return __('Kursa Git', 'astmed-lms');
        }
        
        return __('Kursu Satın Al', 'astmed-lms');
    }
    
    /**
     * Sepete ekleme URL'sini özelleştir
     */
    public function add_to_cart_url() {
        if (!is_user_logged_in()) {
            return wc_get_page_permalink('myaccount');
        }
        
        $course_id = $this->get_course_id();
        if ($course_id && $this->is_user_enrolled(get_current_user_id(), $course_id)) {
            return get_permalink($course_id);
        }
        
        return parent::add_to_cart_url();
    }
    
    /**
     * Kurs bilgilerini al
     */
    public function get_course_info() {
        $course_id = $this->get_course_id();
        if (!$course_id) return null;
        
        $course = get_post($course_id);
        if (!$course) return null;
        
        // Ders sayısı
        $lesson_count = get_posts(array(
            'post_type' => 'astmed_lesson',
            'meta_query' => array(
                array(
                    'key' => '_lesson_course',
                    'value' => $course_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        // Quiz sayısı
        $quiz_count = get_posts(array(
            'post_type' => 'astmed_quiz',
            'meta_query' => array(
                array(
                    'key' => '_quiz_course',
                    'value' => $course_id,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        // Eğitmen bilgisi
        $instructor_id = get_post_meta($course_id, '_course_instructor', true);
        $instructor = $instructor_id ? get_userdata($instructor_id) : null;
        
        return array(
            'course' => $course,
            'lesson_count' => count($lesson_count),
            'quiz_count' => count($quiz_count),
            'instructor' => $instructor,
            'duration' => get_post_meta($course_id, '_course_duration', true),
            'level' => get_post_meta($course_id, '_course_level', true),
            'max_students' => get_post_meta($course_id, '_course_max_students', true),
            'requirements' => get_post_meta($course_id, '_course_requirements', true),
            'outcomes' => get_post_meta($course_id, '_course_outcomes', true)
        );
    }
    
    /**
     * Fiyat bilgisini özelleştir
     */
    public function get_price_html($price = '') {
        $price_html = parent::get_price_html($price);
        
        // Erişim bilgisini ekle
        $access_period = $this->get_access_period();
        
        if ($access_period === 'lifetime') {
            $access_text = __('Yaşam Boyu Erişim', 'astmed-lms');
        } else {
            $periods = array(
                '1_month' => __('1 Ay', 'astmed-lms'),
                '3_months' => __('3 Ay', 'astmed-lms'),
                '6_months' => __('6 Ay', 'astmed-lms'),
                '1_year' => __('1 Yıl', 'astmed-lms')
            );
            $access_text = isset($periods[$access_period]) ? $periods[$access_period] . ' ' . __('Erişim', 'astmed-lms') : '';
        }
        
        if ($access_text) {
            $price_html .= '<span class="course-access-period">' . $access_text . '</span>';
        }
        
        return $price_html;
    }
    
    /**
     * Ürün özet bilgilerini al
     */
    public function get_course_summary() {
        $course_info = $this->get_course_info();
        if (!$course_info) return '';
        
        $summary_parts = array();
        
        if ($course_info['lesson_count']) {
            $summary_parts[] = sprintf(_n('%d Ders', '%d Ders', $course_info['lesson_count'], 'astmed-lms'), $course_info['lesson_count']);
        }
        
        if ($course_info['quiz_count']) {
            $summary_parts[] = sprintf(_n('%d Quiz', '%d Quiz', $course_info['quiz_count'], 'astmed-lms'), $course_info['quiz_count']);
        }
        
        if ($course_info['duration']) {
            $summary_parts[] = sprintf(__('%s Saat', 'astmed-lms'), $course_info['duration']);
        }
        
        if ($course_info['level']) {
            $levels = array(
                'beginner' => __('Başlangıç', 'astmed-lms'),
                'intermediate' => __('Orta', 'astmed-lms'),
                'advanced' => __('İleri', 'astmed-lms'),
                'expert' => __('Uzman', 'astmed-lms')
            );
            if (isset($levels[$course_info['level']])) {
                $summary_parts[] = $levels[$course_info['level']];
            }
        }
        
        if ($this->includes_certificate()) {
            $summary_parts[] = __('Sertifika Dahil', 'astmed-lms');
        }
        
        return implode(' • ', $summary_parts);
    }
    
    /**
     * Kurs öğrenci sayısını al
     */
    public function get_enrolled_count() {
        global $wpdb;
        
        $course_id =