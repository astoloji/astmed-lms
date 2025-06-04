<?php
/**
 * ASTMED LMS Post Types
 * Kurs, Ders, Sınav ve Sertifika post türlerini yönetir
 */

if (!defined('ABSPATH')) exit;

class ASTMED_LMS_Post_Types {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_types'), 5);
        add_action('init', array($this, 'register_taxonomies'), 6);
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'), 10, 2);
        add_filter('manage_astmed_course_posts_columns', array($this, 'course_columns'));
        add_action('manage_astmed_course_posts_custom_column', array($this, 'course_column_content'), 10, 2);
    }
    
    /**
     * Post türlerini kaydet
     */
    public function register_post_types() {
        
        // KURSLAR
        register_post_type('astmed_course', array(
            'labels' => array(
                'name' => __('Kurslar', 'astmed-lms'),
                'singular_name' => __('Kurs', 'astmed-lms'),
                'add_new' => __('Yeni Kurs', 'astmed-lms'),
                'add_new_item' => __('Yeni Kurs Ekle', 'astmed-lms'),
                'edit_item' => __('Kursu Düzenle', 'astmed-lms'),
                'new_item' => __('Yeni Kurs', 'astmed-lms'),
                'view_item' => __('Kursu Görüntüle', 'astmed-lms'),
                'search_items' => __('Kurs Ara', 'astmed-lms'),
                'not_found' => __('Kurs bulunamadı', 'astmed-lms'),
                'not_found_in_trash' => __('Çöp kutusunda kurs yok', 'astmed-lms'),
                'menu_name' => __('Kurslar', 'astmed-lms')
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'astmed-lms',
            'show_in_rest' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'author', 'comments'),
            'has_archive' => true,
            'rewrite' => array('slug' => 'kurs', 'with_front' => false),
            'menu_icon' => 'dashicons-welcome-learn-more',
            'capability_type' => 'course',
            'map_meta_cap' => true,
        ));
        
        // DERSLER
        register_post_type('astmed_lesson', array(
            'labels' => array(
                'name' => __('Dersler', 'astmed-lms'),
                'singular_name' => __('Ders', 'astmed-lms'),
                'add_new' => __('Yeni Ders', 'astmed-lms'),
                'add_new_item' => __('Yeni Ders Ekle', 'astmed-lms'),
                'edit_item' => __('Dersi Düzenle', 'astmed-lms'),
                'new_item' => __('Yeni Ders', 'astmed-lms'),
                'view_item' => __('Dersi Görüntüle', 'astmed-lms'),
                'search_items' => __('Ders Ara', 'astmed-lms'),
                'not_found' => __('Ders bulunamadı', 'astmed-lms'),
                'not_found_in_trash' => __('Çöp kutusunda ders yok', 'astmed-lms'),
                'menu_name' => __('Dersler', 'astmed-lms')
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => 'astmed-lms',
            'show_in_rest' => true,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'author', 'page-attributes'),
            'has_archive' => false,
            'rewrite' => array('slug' => 'ders', 'with_front' => false),
            'capability_type' => 'lesson',
            'map_meta_cap' => true,
            'hierarchical' => false,
        ));
        
        // QUIZLER
        register_post_type('astmed_quiz', array(
            'labels' => array(
                'name' => __('Quizler', 'astmed-lms'),
                'singular_name' => __('Quiz', 'astmed-lms'),
                'add_new' => __('Yeni Quiz', 'astmed-lms'),
                'add_new_item' => __('Yeni Quiz Ekle', 'astmed-lms'),
                'edit_item' => __('Quiz\'i Düzenle', 'astmed-lms'),
                'new_item' => __('Yeni Quiz', 'astmed-lms'),
                'view_item' => __('Quiz\'i Görüntüle', 'astmed-lms'),
                'search_items' => __('Quiz Ara', 'astmed-lms'),
                'not_found' => __('Quiz bulunamadı', 'astmed-lms'),
                'not_found_in_trash' => __('Çöp kutusunda quiz yok', 'astmed-lms'),
                'menu_name' => __('Quizler', 'astmed-lms')
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'astmed-lms',
            'show_in_rest' => false,
            'supports' => array('title', 'editor', 'author'),
            'has_archive' => false,
            'capability_type' => 'quiz',
            'map_meta_cap' => true,
        ));
        
        // SERTİFİKALAR
        register_post_type('astmed_certificate', array(
            'labels' => array(
                'name' => __('Sertifikalar', 'astmed-lms'),
                'singular_name' => __('Sertifika', 'astmed-lms'),
                'add_new' => __('Yeni Sertifika', 'astmed-lms'),
                'add_new_item' => __('Yeni Sertifika Ekle', 'astmed-lms'),
                'edit_item' => __('Sertifikayı Düzenle', 'astmed-lms'),
                'new_item' => __('Yeni Sertifika', 'astmed-lms'),
                'view_item' => __('Sertifikayı Görüntüle', 'astmed-lms'),
                'search_items' => __('Sertifika Ara', 'astmed-lms'),
                'not_found' => __('Sertifika bulunamadı', 'astmed-lms'),
                'not_found_in_trash' => __('Çöp kutusunda sertifika yok', 'astmed-lms'),
                'menu_name' => __('Sertifikalar', 'astmed-lms')
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'astmed-lms',
            'show_in_rest' => false,
            'supports' => array('title', 'editor', 'thumbnail'),
            'has_archive' => false,
            'capability_type' => 'certificate',
            'map_meta_cap' => true,
        ));
        
        // ABONELIKLER (subscription plans)
        register_post_type('astmed_subscription', array(
            'labels' => array(
                'name' => __('Abonelik Planları', 'astmed-lms'),
                'singular_name' => __('Abonelik Planı', 'astmed-lms'),
                'add_new' => __('Yeni Plan', 'astmed-lms'),
                'add_new_item' => __('Yeni Abonelik Planı Ekle', 'astmed-lms'),
                'edit_item' => __('Planı Düzenle', 'astmed-lms'),
                'menu_name' => __('Abonelik Planları', 'astmed-lms')
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'astmed-lms',
            'supports' => array('title', 'editor', 'thumbnail'),
            'capability_type' => 'subscription',
            'map_meta_cap' => true,
        ));
    }
    
    /**
     * Taksonomileri kaydet
     */
    public function register_taxonomies() {
        
        // Kurs kategorileri
        register_taxonomy('course_category', 'astmed_course', array(
            'labels' => array(
                'name' => __('Kurs Kategorileri', 'astmed-lms'),
                'singular_name' => __('Kategori', 'astmed-lms'),
                'search_items' => __('Kategori Ara', 'astmed-lms'),
                'all_items' => __('Tüm Kategoriler', 'astmed-lms'),
                'edit_item' => __('Kategoriyi Düzenle', 'astmed-lms'),
                'update_item' => __('Kategoriyi Güncelle', 'astmed-lms'),
                'add_new_item' => __('Yeni Kategori Ekle', 'astmed-lms'),
                'new_item_name' => __('Yeni Kategori Adı', 'astmed-lms'),
                'menu_name' => __('Kategoriler', 'astmed-lms'),
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'kurs-kategori'),
        ));
        
        // Kurs etiketleri
        register_taxonomy('course_tag', 'astmed_course', array(
            'labels' => array(
                'name' => __('Kurs Etiketleri', 'astmed-lms'),
                'singular_name' => __('Etiket', 'astmed-lms'),
                'search_items' => __('Etiket Ara', 'astmed-lms'),
                'all_items' => __('Tüm Etiketler', 'astmed-lms'),
                'edit_item' => __('Etiketi Düzenle', 'astmed-lms'),
                'update_item' => __('Etiketi Güncelle', 'astmed-lms'),
                'add_new_item' => __('Yeni Etiket Ekle', 'astmed-lms'),
                'new_item_name' => __('Yeni Etiket Adı', 'astmed-lms'),
                'menu_name' => __('Etiketler', 'astmed-lms'),
            ),
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'kurs-etiket'),
        ));
        
        // Zorluk seviyeleri
        register_taxonomy('course_difficulty', 'astmed_course', array(
            'labels' => array(
                'name' => __('Zorluk Seviyeleri', 'astmed-lms'),
                'singular_name' => __('Zorluk Seviyesi', 'astmed-lms'),
                'menu_name' => __('Zorluk Seviyeleri', 'astmed-lms'),
            ),
            'hierarchical' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'zorluk'),
        ));
    }
    
    /**
     * Meta box'ları ekle
     */
    public function add_meta_boxes() {
        
        // Kurs meta box'ları
        add_meta_box(
            'astmed_course_details',
            __('Kurs Detayları', 'astmed-lms'),
            array($this, 'course_details_meta_box'),
            'astmed_course',
            'normal',
            'high'
        );
        
        add_meta_box(
            'astmed_course_pricing',
            __('Fiyatlandırma', 'astmed-lms'),
            array($this, 'course_pricing_meta_box'),
            'astmed_course',
            'side',
            'high'
        );
        
        add_meta_box(
            'astmed_course_access',
            __('Erişim Ayarları', 'astmed-lms'),
            array($this, 'course_access_meta_box'),
            'astmed_course',
            'side',
            'default'
        );
        
        // Ders meta box'ları
        add_meta_box(
            'astmed_lesson_details',
            __('Ders Detayları', 'astmed-lms'),
            array($this, 'lesson_details_meta_box'),
            'astmed_lesson',
            'normal',
            'high'
        );
        
        add_meta_box(
            'astmed_lesson_content',
            __('Ders İçeriği', 'astmed-lms'),
            array($this, 'lesson_content_meta_box'),
            'astmed_lesson',
            'normal',
            'default'
        );
        
        // Quiz meta box'ları
        add_meta_box(
            'astmed_quiz_questions',
            __('Quiz Soruları', 'astmed-lms'),
            array($this, 'quiz_questions_meta_box'),
            'astmed_quiz',
            'normal',
            'high'
        );
        
        add_meta_box(
            'astmed_quiz_settings',
            __('Quiz Ayarları', 'astmed-lms'),
            array($this, 'quiz_settings_meta_box'),
            'astmed_quiz',
            'side',
            'high'
        );
        
        // Sertifika meta box'ları
        add_meta_box(
            'astmed_certificate_design',
            __('Sertifika Tasarımı', 'astmed-lms'),
            array($this, 'certificate_design_meta_box'),
            'astmed_certificate',
            'normal',
            'high'
        );
        
        // Abonelik meta box'ları
        add_meta_box(
            'astmed_subscription_details',
            __('Abonelik Detayları', 'astmed-lms'),
            array($this, 'subscription_details_meta_box'),
            'astmed_subscription',
            'normal',
            'high'
        );
    }
    
    /**
     * Kurs detayları meta box
     */
    public function course_details_meta_box($post) {
        wp_nonce_field('astmed_course_meta_nonce', 'astmed_course_meta_nonce_field');
        
        $duration = get_post_meta($post->ID, '_course_duration', true);
        $level = get_post_meta($post->ID, '_course_level', true);
        $instructor_id = get_post_meta($post->ID, '_course_instructor', true);
        $max_students = get_post_meta($post->ID, '_course_max_students', true);
        $course_intro_video = get_post_meta($post->ID, '_course_intro_video', true);
        $course_requirements = get_post_meta($post->ID, '_course_requirements', true);
        $course_outcomes = get_post_meta($post->ID, '_course_outcomes', true);
        
        $instructors = get_users(array('role' => 'astmed_instructor'));
        ?>
        <table class="form-table">
            <tr>
                <th><label for="course_duration"><?php _e('Kurs Süresi (Saat)', 'astmed-lms'); ?></label></th>
                <td><input type="number" id="course_duration" name="course_duration" value="<?php echo esc_attr($duration); ?>" step="0.5" /></td>
            </tr>
            <tr>
                <th><label for="course_level"><?php _e('Seviye', 'astmed-lms'); ?></label></th>
                <td>
                    <select id="course_level" name="course_level">
                        <option value="beginner" <?php selected($level, 'beginner'); ?>><?php _e('Başlangıç', 'astmed-lms'); ?></option>
                        <option value="intermediate" <?php selected($level, 'intermediate'); ?>><?php _e('Orta', 'astmed-lms'); ?></option>
                        <option value="advanced" <?php selected($level, 'advanced'); ?>><?php _e('İleri', 'astmed-lms'); ?></option>
                        <option value="expert" <?php selected($level, 'expert'); ?>><?php _e('Uzman', 'astmed-lms'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="course_instructor"><?php _e('Eğitmen', 'astmed-lms'); ?></label></th>
                <td>
                    <select id="course_instructor" name="course_instructor">
                        <option value=""><?php _e('Eğitmen Seçin', 'astmed-lms'); ?></option>
                        <?php foreach ($instructors as $instructor) : ?>
                            <option value="<?php echo $instructor->ID; ?>" <?php selected($instructor_id, $instructor->ID); ?>>
                                <?php echo esc_html($instructor->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="course_max_students"><?php _e('Maksimum Öğrenci Sayısı', 'astmed-lms'); ?></label></th>
                <td><input type="number" id="course_max_students" name="course_max_students" value="<?php echo esc_attr($max_students); ?>" min="1" /></td>
            </tr>
            <tr>
                <th><label for="course_intro_video"><?php _e('Tanıtım Videosu URL', 'astmed-lms'); ?></label></th>
                <td><input type="url" id="course_intro_video" name="course_intro_video" value="<?php echo esc_attr($course_intro_video); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="course_requirements"><?php _e('Ön Gereksinimler', 'astmed-lms'); ?></label></th>
                <td><textarea id="course_requirements" name="course_requirements" rows="4" cols="50"><?php echo esc_textarea($course_requirements); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="course_outcomes"><?php _e('Öğrenme Çıktıları', 'astmed-lms'); ?></label></th>
                <td><textarea id="course_outcomes" name="course_outcomes" rows="4" cols="50"><?php echo esc_textarea($course_outcomes); ?></textarea></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Kurs fiyatlandırma meta box
     */
    public function course_pricing_meta_box($post) {
        $price = get_post_meta($post->ID, '_course_price', true);
        $sale_price = get_post_meta($post->ID, '_course_sale_price', true);
        $is_free = get_post_meta($post->ID, '_course_is_free', true);
        $subscription_only = get_post_meta($post->ID, '_course_subscription_only', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="course_is_free" value="1" <?php checked($is_free, '1'); ?> />
                <?php _e('Ücretsiz Kurs', 'astmed-lms'); ?>
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="course_subscription_only" value="1" <?php checked($subscription_only, '1'); ?> />
                <?php _e('Sadece Aboneler İçin', 'astmed-lms'); ?>
            </label>
        </p>
        <p>
            <label for="course_price"><?php _e('Fiyat (₺)', 'astmed-lms'); ?></label><br>
            <input type="number" id="course_price" name="course_price" value="<?php echo esc_attr($price); ?>" step="0.01" style="width: 100%;" />
        </p>
        <p>
            <label for="course_sale_price"><?php _e('İndirimli Fiyat (₺)', 'astmed-lms'); ?></label><br>
            <input type="number" id="course_sale_price" name="course_sale_price" value="<?php echo esc_attr($sale_price); ?>" step="0.01" style="width: 100%;" />
        </p>
        <?php
    }
    
    /**
     * Kurs erişim meta box
     */
    public function course_access_meta_box($post) {
        $access_type = get_post_meta($post->ID, '_course_access_type', true);
        $drip_content = get_post_meta($post->ID, '_course_drip_content', true);
        $course_status = get_post_meta($post->ID, '_course_status', true);
        ?>
        <p>
            <label for="course_access_type"><?php _e('Erişim Türü', 'astmed-lms'); ?></label><br>
            <select id="course_access_type" name="course_access_type" style="width: 100%;">
                <option value="open" <?php selected($access_type, 'open'); ?>><?php _e('Açık Erişim', 'astmed-lms'); ?></option>
                <option value="enrollment" <?php selected($access_type, 'enrollment'); ?>><?php _e('Kayıt Gerekli', 'astmed-lms'); ?></option>
                <option value="purchase" <?php selected($access_type, 'purchase'); ?>><?php _e('Satın Alma Gerekli', 'astmed-lms'); ?></option>
                <option value="subscription" <?php selected($access_type, 'subscription'); ?>><?php _e('Abonelik Gerekli', 'astmed-lms'); ?></option>
            </select>
        </p>
        <p>
            <label>
                <input type="checkbox" name="course_drip_content" value="1" <?php checked($drip_content, '1'); ?> />
                <?php _e('İçerik Kademeli Açılsın', 'astmed-lms'); ?>
            </label>
        </p>
        <p>
            <label for="course_status"><?php _e('Durum', 'astmed-lms'); ?></label><br>
            <select id="course_status" name="course_status" style="width: 100%;">
                <option value="active" <?php selected($course_status, 'active'); ?>><?php _e('Aktif', 'astmed-lms'); ?></option>
                <option value="coming_soon" <?php selected($course_status, 'coming_soon'); ?>><?php _e('Yakında', 'astmed-lms'); ?></option>
                <option value="draft" <?php selected($course_status, 'draft'); ?>><?php _e('Taslak', 'astmed-lms'); ?></option>
                <option value="closed" <?php selected($course_status, 'closed'); ?>><?php _e('Kapalı', 'astmed-lms'); ?></option>
            </select>
        </p>
        <?php
    }
    
    /**
     * Ders detayları meta box
     */
    public function lesson_details_meta_box($post) {
        wp_nonce_field('astmed_lesson_meta_nonce', 'astmed_lesson_meta_nonce_field');
        
        $course_id = get_post_meta($post->ID, '_lesson_course', true);
        $lesson_type = get_post_meta($post->ID, '_lesson_type', true);
        $duration = get_post_meta($post->ID, '_lesson_duration', true);
        $is_preview = get_post_meta($post->ID, '_lesson_preview', true);
        
        $courses = get_posts(array(
            'post_type' => 'astmed_course',
            'numberposts' => -1,
            'post_status' => 'publish'
        ));
        ?>
        <table class="form-table">
            <tr>
                <th><label for="lesson_course"><?php _e('Bağlı Kurs', 'astmed-lms'); ?></label></th>
                <td>
                    <select id="lesson_course" name="lesson_course" required>
                        <option value=""><?php _e('Kurs Seçin', 'astmed-lms'); ?></option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?php echo $course->ID; ?>" <?php selected($course_id, $course->ID); ?>>
                                <?php echo esc_html($course->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="lesson_type"><?php _e('Ders Türü', 'astmed-lms'); ?></label></th>
                <td>
                    <select id="lesson_type" name="lesson_type">
                        <option value="video" <?php selected($lesson_type, 'video'); ?>><?php _e('Video', 'astmed-lms'); ?></option>
                        <option value="text" <?php selected($lesson_type, 'text'); ?>><?php _e('Metin', 'astmed-lms'); ?></option>
                        <option value="audio" <?php selected($lesson_type, 'audio'); ?>><?php _e('Ses', 'astmed-lms'); ?></option>
                        <option value="document" <?php selected($lesson_type, 'document'); ?>><?php _e('Belge', 'astmed-lms'); ?></option>
                        <option value="interactive" <?php selected($lesson_type, 'interactive'); ?>><?php _e('Etkileşimli', 'astmed-lms'); ?></option>
                        <option value="case_study" <?php selected($lesson_type, 'case_study'); ?>><?php _e('Vaka Analizi', 'astmed-lms'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="lesson_duration"><?php _e('Süre (Dakika)', 'astmed-lms'); ?></label></th>
                <td><input type="number" id="lesson_duration" name="lesson_duration" value="<?php echo esc_attr($duration); ?>" min="1" /></td>
            </tr>
            <tr>
                <th></th>
                <td>
                    <label>
                        <input type="checkbox" name="lesson_preview" value="1" <?php checked($is_preview, '1'); ?> />
                        <?php _e('Önizleme dersi (Herkes görebilir)', 'astmed-lms'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Ders içeriği meta box
     */
    public function lesson_content_meta_box($post) {
        $video_url = get_post_meta($post->ID, '_lesson_video_url', true);
        $video_provider = get_post_meta($post->ID, '_lesson_video_provider', true);
        $resources = get_post_meta($post->ID, '_lesson_resources', true);
        $attachments = get_post_meta($post->ID, '_lesson_attachments', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="lesson_video_provider"><?php _e('Video Sağlayıcı', 'astmed-lms'); ?></label></th>
                <td>
                    <select id="lesson_video_provider" name="lesson_video_provider">
                        <option value="youtube" <?php selected($video_provider, 'youtube'); ?>><?php _e('YouTube', 'astmed-lms'); ?></option>
                        <option value="vimeo" <?php selected($video_provider, 'vimeo'); ?>><?php _e('Vimeo', 'astmed-lms'); ?></option>
                        <option value="wistia" <?php selected($video_provider, 'wistia'); ?>><?php _e('Wistia', 'astmed-lms'); ?></option>
                        <option value="self_hosted" <?php selected($video_provider, 'self_hosted'); ?>><?php _e('Kendi Sunucum', 'astmed-lms'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="lesson_video_url"><?php _e('Video URL', 'astmed-lms'); ?></label></th>
                <td><input type="url" id="lesson_video_url" name="lesson_video_url" value="<?php echo esc_attr($video_url); ?>" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="lesson_resources"><?php _e('Ek Kaynaklar', 'astmed-lms'); ?></label></th>
                <td><textarea id="lesson_resources" name="lesson_resources" rows="4" cols="50" placeholder="Her satırda bir kaynak (isim|url)"><?php echo esc_textarea($resources); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="lesson_attachments"><?php _e('Ek Dosyalar', 'astmed-lms'); ?></label></th>
                <td>
                    <input type="text" id="lesson_attachments" name="lesson_attachments" value="<?php echo esc_attr($attachments); ?>" readonly />
                    <button type="button" class="button" id="upload_attachments"><?php _e('Dosya Seç', 'astmed-lms'); ?></button>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Quiz soruları meta box
     */
    public function quiz_questions_meta_box($post) {
        wp_nonce_field('astmed_quiz_meta_nonce', 'astmed_quiz_meta_nonce_field');
        
        $questions = get_post_meta($post->ID, '_quiz_questions', true);
        if (!is_array($questions)) $questions = array();
        ?>
        <div id="quiz-questions-container">
            <?php foreach ($questions as $index => $question) : ?>
                <div class="question-item" data-index="<?php echo $index; ?>">
                    <?php $this->render_question_fields($question, $index); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" id="add-question"><?php _e('Soru Ekle', 'astmed-lms'); ?></button>
        
        <script type="text/template" id="question-template">
            <div class="question-item" data-index="{{index}}">
                <?php $this->render_question_fields(array(), '{{index}}'); ?>
            </div>
        </script>
        <?php
    }
    
    /**
     * Quiz ayarları meta box
     */
    public function quiz_settings_meta_box($post) {
        $time_limit = get_post_meta($post->ID, '_quiz_time_limit', true);
        $pass_percentage = get_post_meta($post->ID, '_quiz_pass_percentage', true);
        $max_attempts = get_post_meta($post->ID, '_quiz_max_attempts', true);
        $randomize = get_post_meta($post->ID, '_quiz_randomize', true);
        $show_correct_answer = get_post_meta($post->ID, '_quiz_show_correct_answer', true);
        ?>
        <p>
            <label for="quiz_time_limit"><?php _e('Süre Sınırı (Dakika)', 'astmed-lms'); ?></label><br>
            <input type="number" id="quiz_time_limit" name="quiz_time_limit" value="<?php echo esc_attr($time_limit); ?>" style="width: 100%;" />
        </p>
        <p>
            <label for="quiz_pass_percentage"><?php _e('Geçme Notu (%)', 'astmed-lms'); ?></label><br>
            <input type="number" id="quiz_pass_percentage" name="quiz_pass_percentage" value="<?php echo esc_attr($pass_percentage ?: 70); ?>" min="0" max="100" style="width: 100%;" />
        </p>
        <p>
            <label for="quiz_max_attempts"><?php _e('Maksimum Deneme', 'astmed-lms'); ?></label><br>
            <input type="number" id="quiz_max_attempts" name="quiz_max_attempts" value="<?php echo esc_attr($max_attempts ?: 3); ?>" min="1" style="width: 100%;" />
        </p>
        <p>
            <label>
                <input type="checkbox" name="quiz_randomize" value="1" <?php checked($randomize, '1'); ?> />
                <?php _e('Soruları Karıştır', 'astmed-lms'); ?>
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="quiz_show_correct_answer" value="1" <?php checked($show_correct_answer, '1'); ?> />
                <?php _e('Doğru Cevapları Göster', 'astmed-lms'); ?>
            </label>
        </p>
        <?php
    }
    
    /**
     * Sertifika tasarım meta box
     */
    public function certificate_design_meta_box($post) {
        wp_nonce_field('astmed_certificate_meta_nonce', 'astmed_certificate_meta_nonce_field');
        
        $template = get_post_meta($post->ID, '_certificate_template', true);
        $orientation = get_post_meta($post->ID, '_certificate_orientation', true);
        $background_image = get_post_meta($post->ID, '_certificate_background', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="certificate_template"><?php _e('Şablon', 'astmed-lms'); ?></label></th>
                <td>
                    <select id="certificate_template" name="certificate_template">
                        <option value="default" <?php selected($template, 'default'); ?>><?php _e('Varsayılan', 'astmed-lms'); ?></option>
                        <option value="modern" <?php selected($template, 'modern'); ?>><?php _e('Modern', 'astmed-lms'); ?></option>
                        <option value="classic" <?php selected($template, 'classic'); ?>><?php _e('Klasik', 'astmed-lms'); ?></option>
                        <option value="medical" <?php selected($template, 'medical'); ?>><?php _e('Medikal', 'astmed-lms'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="certificate_orientation"><?php _e('Yönelim', 'astmed-lms'); ?></label></th>
                <td>
                    <select id="certificate_orientation" name="certificate_orientation">
                        <option value="landscape" <?php selected($orientation, 'landscape'); ?>><?php _e('Yatay', 'astmed-lms'); ?></option>
                        <option value="portrait" <?php selected($orientation, 'portrait'); ?>><?php _e('Dikey', 'astmed-lms'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="certificate_background"><?php _e('Arka Plan Resmi', 'astmed-lms'); ?></label></th>
                <td>
                    <input type="text" id="certificate_background" name="certificate_background" value="<?php echo esc_attr($background_image); ?>" readonly />
                    <button type="button" class="button" id="upload_background"><?php _e('Resim Seç', 'astmed-lms'); ?></button>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Abonelik detayları meta box
     */
    public function subscription_details_meta_box($post) {
        wp_nonce_field('astmed_subscription_meta_nonce', 'astmed_subscription_meta_nonce_field');
        
        $price = get_post_meta($post->ID, '_subscription_price', true);
        $billing_cycle = get_post_meta($post->ID, '_subscription_billing_cycle', true);
        $trial_days = get_post_meta($post->ID, '_subscription_trial_days', true);
        $features = get_post_meta($post->ID, '_subscription_features', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="subscription_price"><?php _e('Fiyat (₺)', 'astmed-lms'); ?></label></th>
                <td><input type="number" id="subscription_price" name="subscription_price" value="<?php echo esc_attr($price); ?>" step="0.01" /></td>
            </tr>
            <tr>
                <th><label for="subscription_billing_cycle"><?php _e('Faturalandırma Döngüsü', 'astmed-lms'); ?></label></th>
                <td>
                    <select id="subscription_billing_cycle" name="subscription_billing_cycle">
                        <option value="monthly" <?php selected($billing_cycle, 'monthly'); ?>><?php _e('Aylık', 'astmed-lms'); ?></option>
                        <option value="quarterly" <?php selected($billing_cycle, 'quarterly'); ?>><?php _e('3 Aylık', 'astmed-lms'); ?></option>
                        <option value="yearly" <?php selected($billing_cycle, 'yearly'); ?>><?php _e('Yıllık', 'astmed-lms'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="subscription_trial_days"><?php _e('Deneme Süresi (Gün)', 'astmed-lms'); ?></label></th>
                <td><input type="number" id="subscription_trial_days" name="subscription_trial_days" value="<?php echo esc_attr($trial_days); ?>" min="0" /></td>
            </tr>
            <tr>
                <th><label for="subscription_features"><?php _e('Özellikler', 'astmed-lms'); ?></label></th>
                <td><textarea id="subscription_features" name="subscription_features" rows="5" cols="50" placeholder="Her satırda bir özellik"><?php echo esc_textarea($features); ?></textarea></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Soru alanlarını render et
     */
    private function render_question_fields($question = array(), $index = 0) {
        $question_text = isset($question['question']) ? $question['question'] : '';
        $question_type = isset($question['type']) ? $question['type'] : 'multiple_choice';
        $answers = isset($question['answers']) ? $question['answers'] : array();
        $correct_answer = isset($question['correct']) ? $question['correct'] : '';
        ?>
        <div class="question-header">
            <h4><?php printf(__('Soru %d', 'astmed-lms'), $index + 1); ?></h4>
            <button type="button" class="button-link-delete remove-question"><?php _e('Sil', 'astmed-lms'); ?></button>
        </div>
        <table class="form-table">
            <tr>
                <th><label><?php _e('Soru Metni', 'astmed-lms'); ?></label></th>
                <td><textarea name="questions[<?php echo $index; ?>][question]" rows="3" cols="50"><?php echo esc_textarea($question_text); ?></textarea></td>
            </tr>
            <tr>
                <th><label><?php _e('Soru Türü', 'astmed-lms'); ?></label></th>
                <td>
                    <select name="questions[<?php echo $index; ?>][type]" class="question-type">
                        <option value="multiple_choice" <?php selected($question_type, 'multiple_choice'); ?>><?php _e('Çoktan Seçmeli', 'astmed-lms'); ?></option>
                        <option value="true_false" <?php selected($question_type, 'true_false'); ?>><?php _e('Doğru/Yanlış', 'astmed-lms'); ?></option>
                        <option value="fill_blank" <?php selected($question_type, 'fill_blank'); ?>><?php _e('Boşluk Doldurma', 'astmed-lms'); ?></option>
                        <option value="essay" <?php selected($question_type, 'essay'); ?>><?php _e('Açık Uçlu', 'astmed-lms'); ?></option>
                        <option value="case_analysis" <?php selected($question_type, 'case_analysis'); ?>><?php _e('Vaka Analizi', 'astmed-lms'); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="answers-row">
                <th><label><?php _e('Cevap Seçenekleri', 'astmed-lms'); ?></label></th>
                <td>
                    <div class="answers-container">
                        <?php for ($i = 0; $i < 4; $i++) : ?>
                            <div class="answer-option">
                                <input type="radio" name="questions[<?php echo $index; ?>][correct]" value="<?php echo $i; ?>" <?php checked($correct_answer, $i); ?> />
                                <input type="text" name="questions[<?php echo $index; ?>][answers][]" value="<?php echo isset($answers[$i]) ? esc_attr($answers[$i]) : ''; ?>" placeholder="<?php printf(__('Seçenek %s', 'astmed-lms'), chr(65 + $i)); ?>" />
                            </div>
                        <?php endfor; ?>
                    </div>
                </td>
            </tr>
            <tr class="explanation-row">
                <th><label><?php _e('Açıklama', 'astmed-lms'); ?></label></th>
                <td><textarea name="questions[<?php echo $index; ?>][explanation]" rows="2" cols="50" placeholder="Bu sorunun açıklaması..."><?php echo isset($question['explanation']) ? esc_textarea($question['explanation']) : ''; ?></textarea></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Meta box verilerini kaydet
     */
    public function save_meta_boxes($post_id, $post) {
        // Otomatik kaydetmeyi atla
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        
        // Yetki kontrolü
        if (!current_user_can('edit_post', $post_id)) return;
        
        // Post türüne göre kaydet
        switch ($post->post_type) {
            case 'astmed_course':
                $this->save_course_meta($post_id);
                break;
            case 'astmed_lesson':
                $this->save_lesson_meta($post_id);
                break;
            case 'astmed_quiz':
                $this->save_quiz_meta($post_id);
                break;
            case 'astmed_certificate':
                $this->save_certificate_meta($post_id);
                break;
            case 'astmed_subscription':
                $this->save_subscription_meta($post_id);
                break;
        }
    }
    
    /**
     * Kurs meta verilerini kaydet
     */
    private function save_course_meta($post_id) {
        if (!isset($_POST['astmed_course_meta_nonce_field']) || 
            !wp_verify_nonce($_POST['astmed_course_meta_nonce_field'], 'astmed_course_meta_nonce')) {
            return;
        }
        
        $fields = array(
            'course_duration' => 'sanitize_text_field',
            'course_level' => 'sanitize_text_field',
            'course_instructor' => 'absint',
            'course_max_students' => 'absint',
            'course_intro_video' => 'esc_url_raw',
            'course_requirements' => 'sanitize_textarea_field',
            'course_outcomes' => 'sanitize_textarea_field',
            'course_price' => 'floatval',
            'course_sale_price' => 'floatval',
            'course_access_type' => 'sanitize_text_field',
            'course_status' => 'sanitize_text_field'
        );
        
        foreach ($fields as $field => $sanitize_func) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, $sanitize_func($_POST[$field]));
            }
        }
        
        // Checkbox alanları
        $checkbox_fields = array('course_is_free', 'course_subscription_only', 'course_drip_content');
        foreach ($checkbox_fields as $field) {
            update_post_meta($post_id, '_' . $field, isset($_POST[$field]) ? '1' : '0');
        }
    }
    
    /**
     * Ders meta verilerini kaydet
     */
    private function save_lesson_meta($post_id) {
        if (!isset($_POST['astmed_lesson_meta_nonce_field']) || 
            !wp_verify_nonce($_POST['astmed_lesson_meta_nonce_field'], 'astmed_lesson_meta_nonce')) {
            return;
        }
        
        $fields = array(
            'lesson_course' => 'absint',
            'lesson_type' => 'sanitize_text_field',
            'lesson_duration' => 'absint',
            'lesson_video_provider' => 'sanitize_text_field',
            'lesson_video_url' => 'esc_url_raw',
            'lesson_resources' => 'sanitize_textarea_field',
            'lesson_attachments' => 'sanitize_text_field'
        );
        
        foreach ($fields as $field => $sanitize_func) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, $sanitize_func($_POST[$field]));
            }
        }
        
        update_post_meta($post_id, '_lesson_preview', isset($_POST['lesson_preview']) ? '1' : '0');
    }
    
    /**
     * Quiz meta verilerini kaydet
     */
    private function save_quiz_meta($post_id) {
        if (!isset($_POST['astmed_quiz_meta_nonce_field']) || 
            !wp_verify_nonce($_POST['astmed_quiz_meta_nonce_field'], 'astmed_quiz_meta_nonce')) {
            return;
        }
        
        // Quiz ayarları
        $fields = array(
            'quiz_time_limit' => 'absint',
            'quiz_pass_percentage' => 'absint',
            'quiz_max_attempts' => 'absint'
        );
        
        foreach ($fields as $field => $sanitize_func) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, $sanitize_func($_POST[$field]));
            }
        }
        
        // Quiz seçenekleri
        $checkbox_fields = array('quiz_randomize', 'quiz_show_correct_answer');
        foreach ($checkbox_fields as $field) {
            update_post_meta($post_id, '_' . $field, isset($_POST[$field]) ? '1' : '0');
        }
        
        // Soruları kaydet
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            $questions = array();
            foreach ($_POST['questions'] as $question_data) {
                if (!empty($question_data['question'])) {
                    $question = array(
                        'question' => sanitize_textarea_field($question_data['question']),
                        'type' => sanitize_text_field($question_data['type']),
                        'answers' => array_map('sanitize_text_field', $question_data['answers']),
                        'correct' => sanitize_text_field($question_data['correct']),
                        'explanation' => sanitize_textarea_field($question_data['explanation'])
                    );
                    $questions[] = $question;
                }
            }
            update_post_meta($post_id, '_quiz_questions', $questions);
        }
    }
    
    /**
     * Sertifika meta verilerini kaydet
     */
    private function save_certificate_meta($post_id) {
        if (!isset($_POST['astmed_certificate_meta_nonce_field']) || 
            !wp_verify_nonce($_POST['astmed_certificate_meta_nonce_field'], 'astmed_certificate_meta_nonce')) {
            return;
        }
        
        $fields = array(
            'certificate_template' => 'sanitize_text_field',
            'certificate_orientation' => 'sanitize_text_field',
            'certificate_background' => 'esc_url_raw'
        );
        
        foreach ($fields as $field => $sanitize_func) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, $sanitize_func($_POST[$field]));
            }
        }
    }
    
    /**
     * Abonelik meta verilerini kaydet
     */
    private function save_subscription_meta($post_id) {
        if (!isset($_POST['astmed_subscription_meta_nonce_field']) || 
            !wp_verify_nonce($_POST['astmed_subscription_meta_nonce_field'], 'astmed_subscription_meta_nonce')) {
            return;
        }
        
        $fields = array(
            'subscription_price' => 'floatval',
            'subscription_billing_cycle' => 'sanitize_text_field',
            'subscription_trial_days' => 'absint',
            'subscription_features' => 'sanitize_textarea_field'
        );
        
        foreach ($fields as $field => $sanitize_func) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, $sanitize_func($_POST[$field]));
            }
        }
    }
    
    /**
     * Kurs liste sütunları
     */
    public function course_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = $columns['title'];
        $new_columns['instructor'] = __('Eğitmen', 'astmed-lms');
        $new_columns['students'] = __('Öğrenci Sayısı', 'astmed-lms');
        $new_columns['price'] = __('Fiyat', 'astmed-lms');
        $new_columns['status'] = __('Durum', 'astmed-lms');
        $new_columns['date'] = $columns['date'];
        
        return $new_columns;
    }
    
    /**
     * Kurs liste sütun içerikleri
     */
    public function course_column_content($column, $post_id) {
        switch ($column) {
            case 'instructor':
                $instructor_id = get_post_meta($post_id, '_course_instructor', true);
                if ($instructor_id) {
                    $instructor = get_userdata($instructor_id);
                    echo $instructor ? esc_html($instructor->display_name) : __('Bilinmiyor', 'astmed-lms');
                } else {
                    echo '—';
                }
                break;
                
            case 'students':
                // Bu daha sonra gerçek öğrenci sayısı ile doldurulacak
                echo '0';
                break;
                
            case 'price':
                $price = get_post_meta($post_id, '_course_price', true);
                $is_free = get_post_meta($post_id, '_course_is_free', true);
                $subscription_only = get_post_meta($post_id, '_course_subscription_only', true);
                
                if ($is_free) {
                    echo '<span class="course-free">' . __('Ücretsiz', 'astmed-lms') . '</span>';
                } elseif ($subscription_only) {
                    echo '<span class="course-subscription">' . __('Sadece Aboneler', 'astmed-lms') . '</span>';
                } elseif ($price) {
                    echo '<span class="course-price">₺' . number_format($price, 2) . '</span>';
                } else {
                    echo '—';
                }
                break;
                
            case 'status':
                $status = get_post_meta($post_id, '_course_status', true) ?: 'active';
                $statuses = array(
                    'active' => array('label' => __('Aktif', 'astmed-lms'), 'color' => 'green'),
                    'coming_soon' => array('label' => __('Yakında', 'astmed-lms'), 'color' => 'orange'),
                    'draft' => array('label' => __('Taslak', 'astmed-lms'), 'color' => 'gray'),
                    'closed' => array('label' => __('Kapalı', 'astmed-lms'), 'color' => 'red')
                );
                
                if (isset($statuses[$status])) {
                    printf(
                        '<span class="course-status course-status-%s" style="color: %s;">%s</span>',
                        esc_attr($status),
                        esc_attr($statuses[$status]['color']),
                        esc_html($statuses[$status]['label'])
                    );
                }
                break;
        }
    }
}