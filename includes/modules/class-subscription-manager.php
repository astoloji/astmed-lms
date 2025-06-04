<?php
/**
 * ASTMED LMS Roles Manager
 * Kullanıcı rollerini ve yetkilerini yönetir
 */

if (!defined('ABSPATH')) exit;

class ASTMED_LMS_Roles_Manager {
    
    public function __construct() {
        add_action('init', array($this, 'maybe_create_roles'));
        add_filter('user_has_cap', array($this, 'check_user_capabilities'), 10, 4);
        add_action('user_register', array($this, 'set_default_role'));
        add_action('wp_login', array($this, 'track_login'), 10, 2);
    }
    
    /**
     * Rolleri oluştur (sadece gerektiğinde)
     */
    public function maybe_create_roles() {
        if (!get_option('astmed_lms_roles_created')) {
            self::create_roles();
            update_option('astmed_lms_roles_created', true);
        }
    }
    
    /**
     * LMS rollerini oluştur
     */
    public static function create_roles() {
        
        // ASTMED Öğrenci Rolü
        add_role('astmed_student', __('ASTMED Öğrenci', 'astmed-lms'), array(
            'read' => true,
            'astmed_access_courses' => true,
            'astmed_take_quizzes' => true,
            'astmed_view_progress' => true,
            'astmed_download_certificates' => true,
            'astmed_comment_courses' => true,
            'astmed_rate_courses' => true,
        ));
        
        // ASTMED Eğitmen Rolü
        add_role('astmed_instructor', __('ASTMED Eğitmen', 'astmed-lms'), array(
            'read' => true,
            'edit_posts' => true,
            'upload_files' => true,
            'publish_posts' => true,
            'astmed_access_courses' => true,
            'astmed_create_courses' => true,
            'astmed_edit_own_courses' => true,
            'astmed_create_lessons' => true,
            'astmed_edit_own_lessons' => true,
            'astmed_create_quizzes' => true,
            'astmed_edit_own_quizzes' => true,
            'astmed_view_student_progress' => true,
            'astmed_grade_assignments' => true,
            'astmed_manage_enrollments' => true,
            'astmed_export_reports' => true,
            'astmed_communicate_students' => true,
        ));
        
        // ASTMED Yönetici Rolü
        add_role('astmed_admin', __('ASTMED Yönetici', 'astmed-lms'), array(
            'read' => true,
            'edit_posts' => true,
            'edit_others_posts' => true,
            'publish_posts' => true,
            'manage_categories' => true,
            'upload_files' => true,
            'manage_options' => true,
            'astmed_access_courses' => true,
            'astmed_manage_all_courses' => true,
            'astmed_manage_all_lessons' => true,
            'astmed_manage_all_quizzes' => true,
            'astmed_manage_users' => true,
            'astmed_manage_enrollments' => true,
            'astmed_manage_subscriptions' => true,
            'astmed_manage_payments' => true,
            'astmed_view_all_reports' => true,
            'astmed_export_data' => true,
            'astmed_system_settings' => true,
            'astmed_manage_certificates' => true,
        ));
        
        // Kurumsal Yönetici Rolü (Çoklu kullanıcı hesapları için)
        add_role('astmed_corporate_admin', __('ASTMED Kurumsal Yönetici', 'astmed-lms'), array(
            'read' => true,
            'astmed_access_courses' => true,
            'astmed_manage_team_enrollments' => true,
            'astmed_view_team_progress' => true,
            'astmed_export_team_reports' => true,
            'astmed_manage_team_users' => true,
            'astmed_bulk_operations' => true,
        ));
        
        // Ana WordPress rollerine LMS yetkilerini ekle
        self::add_caps_to_existing_roles();
        
        astmed_lms_log('LMS roles created successfully');
    }
    
    /**
     * Mevcut WordPress rollerine LMS yetkilerini ekle
     */
    private static function add_caps_to_existing_roles() {
        
        // Administrator'e tüm LMS yetkilerini ver
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_caps = array(
                'astmed_access_courses',
                'astmed_manage_all_courses',
                'astmed_manage_all_lessons', 
                'astmed_manage_all_quizzes',
                'astmed_manage_users',
                'astmed_manage_enrollments',
                'astmed_manage_subscriptions',
                'astmed_manage_payments',
                'astmed_view_all_reports',
                'astmed_export_data',
                'astmed_system_settings',
                'astmed_manage_certificates',
            );
            
            foreach ($admin_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Editor'e kurs yönetimi yetkilerini ver
        $editor_role = get_role('editor');
        if ($editor_role) {
            $editor_caps = array(
                'astmed_access_courses',
                'astmed_create_courses',
                'astmed_edit_own_courses',
                'astmed_create_lessons',
                'astmed_edit_own_lessons',
                'astmed_view_student_progress',
            );
            
            foreach ($editor_caps as $cap) {
                $editor_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Kullanıcı yetkilerini kontrol et
     */
    public function check_user_capabilities($allcaps, $caps, $args, $user) {
        // Eğer user nesnesi yoksa çık
        if (!$user instanceof WP_User) {
            return $allcaps;
        }
        
        // İstenen yetki
        $capability = isset($args[0]) ? $args[0] : '';
        
        // Object ID (post, user, vs.)
        $object_id = isset($args[2]) ? $args[2] : 0;
        
        switch ($capability) {
            case 'edit_course':
            case 'delete_course':
                return $this->check_course_capability($allcaps, $capability, $object_id, $user);
                
            case 'edit_lesson':
            case 'delete_lesson':
                return $this->check_lesson_capability($allcaps, $capability, $object_id, $user);
                
            case 'edit_quiz':
            case 'delete_quiz':
                return $this->check_quiz_capability($allcaps, $capability, $object_id, $user);
                
            case 'view_course':
                return $this->check_course_access($allcaps, $object_id, $user);
                
            case 'take_quiz':
                return $this->check_quiz_access($allcaps, $object_id, $user);
        }
        
        return $allcaps;
    }
    
    /**
     * Kurs yetki kontrolü
     */
    private function check_course_capability($allcaps, $capability, $course_id, $user) {
        // Admin her şeyi yapabilir
        if (user_can($user, 'manage_options') || user_can($user, 'astmed_manage_all_courses')) {
            $allcaps[$capability] = true;
            return $allcaps;
        }
        
        // Kursun sahibi mi kontrol et
        if ($course_id && user_can($user, 'astmed_edit_own_courses')) {
            $course_instructor = get_post_meta($course_id, '_course_instructor', true);
            if ($course_instructor == $user->ID) {
                $allcaps[$capability] = true;
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Ders yetki kontrolü
     */
    private function check_lesson_capability($allcaps, $capability, $lesson_id, $user) {
        // Admin her şeyi yapabilir
        if (user_can($user, 'manage_options') || user_can($user, 'astmed_manage_all_lessons')) {
            $allcaps[$capability] = true;
            return $allcaps;
        }
        
        // Dersin bağlı olduğu kursun sahibi mi kontrol et
        if ($lesson_id && user_can($user, 'astmed_edit_own_lessons')) {
            $course_id = get_post_meta($lesson_id, '_lesson_course', true);
            if ($course_id) {
                $course_instructor = get_post_meta($course_id, '_course_instructor', true);
                if ($course_instructor == $user->ID) {
                    $allcaps[$capability] = true;
                }
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Quiz yetki kontrolü
     */
    private function check_quiz_capability($allcaps, $capability, $quiz_id, $user) {
        // Admin her şeyi yapabilir
        if (user_can($user, 'manage_options') || user_can($user, 'astmed_manage_all_quizzes')) {
            $allcaps[$capability] = true;
            return $allcaps;
        }
        
        // Quiz'in sahibi mi kontrol et
        if ($quiz_id && user_can($user, 'astmed_edit_own_quizzes')) {
            $quiz_author = get_post_field('post_author', $quiz_id);
            if ($quiz_author == $user->ID) {
                $allcaps[$capability] = true;
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Kursa erişim kontrolü
     */
    private function check_course_access($allcaps, $course_id, $user) {
        if (!$course_id) {
            return $allcaps;
        }
        
        // Admin ve eğitmen erişimi
        if (user_can($user, 'manage_options') || 
            user_can($user, 'astmed_manage_all_courses') ||
            user_can($user, 'astmed_instructor')) {
            $allcaps['view_course'] = true;
            return $allcaps;
        }
        
        // Kurs erişim türünü kontrol et
        $access_type = get_post_meta($course_id, '_course_access_type', true);
        $is_free = get_post_meta($course_id, '_course_is_free', true);
        
        switch ($access_type) {
            case 'open':
                $allcaps['view_course'] = true;
                break;
                
            case 'enrollment':
                if ($this->is_user_enrolled($user->ID, $course_id)) {
                    $allcaps['view_course'] = true;
                }
                break;
                
            case 'purchase':
                if ($is_free || $this->has_user_purchased($user->ID, $course_id)) {
                    $allcaps['view_course'] = true;
                }
                break;
                
            case 'subscription':
                if ($this->has_active_subscription($user->ID)) {
                    $allcaps['view_course'] = true;
                }
                break;
        }
        
        return $allcaps;
    }
    
    /**
     * Quiz erişim kontrolü
     */
    private function check_quiz_access($allcaps, $quiz_id, $user) {
        if (!$quiz_id) {
            return $allcaps;
        }
        
        // Quiz'e erişim için önce kursa erişim gerekli
        $course_id = get_post_meta($quiz_id, '_quiz_course', true);
        if ($course_id && !user_can($user, 'view_course', $course_id)) {
            return $allcaps;
        }
        
        // Quiz deneme sınırı kontrolü
        $max_attempts = get_post_meta($quiz_id, '_quiz_max_attempts', true) ?: 3;
        $user_attempts = $this->get_user_quiz_attempts($user->ID, $quiz_id);
        
        if ($user_attempts < $max_attempts) {
            $allcaps['take_quiz'] = true;
        }
        
        return $allcaps;
    }
    
    /**
     * Kullanıcı kursa kayıtlı mı?
     */
    private function is_user_enrolled($user_id, $course_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}astmed_enrollments 
             WHERE user_id = %d AND course_id = %d AND status = 'active'",
            $user_id, $course_id
        ));
        
        return !empty($result);
    }
    
    /**
     * Kullanıcı kursu satın almış mı?
     */
    private function has_user_purchased($user_id, $course_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}astmed_enrollments 
             WHERE user_id = %d AND course_id = %d 
             AND enrollment_type IN ('purchase', 'subscription') 
             AND status = 'active'",
            $user_id, $course_id
        ));
        
        return !empty($result);
    }
    
    /**
     * Kullanıcının aktif aboneliği var mı?
     */
    private function has_active_subscription($user_id) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}astmed_subscriptions 
             WHERE user_id = %d AND status = 'active' 
             AND current_period_end > NOW()",
            $user_id
        ));
        
        return !empty($result);
    }
    
    /**
     * Kullanıcının quiz deneme sayısını al
     */
    private function get_user_quiz_attempts($user_id, $quiz_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}astmed_quiz_attempts 
             WHERE user_id = %d AND quiz_id = %d",
            $user_id, $quiz_id
        ));
        
        return (int) $count;
    }
    
    /**
     * Yeni kayıtta varsayılan rol ata
     */
    public function set_default_role($user_id) {
        $user = new WP_User($user_id);
        
        // Eğer hiç rolü yoksa öğrenci yap
        if (empty($user->roles)) {
            $user->add_role('astmed_student');
        }
    }
    
    /**
     * Giriş aktivitesini takip et
     */
    public function track_login($user_login, $user) {
        // Son giriş zamanını kaydet
        update_user_meta($user->ID, 'last_login', current_time('mysql'));
        
        // Aktivite kaydı
        $this->log_user_activity($user->ID, 'login', null, null, array(
            'ip_address' => $this->get_user_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        ));
    }
    
    /**
     * Kullanıcı aktivitesi kaydet
     */
    private function log_user_activity($user_id, $activity_type, $object_id = null, $object_type = null, $extra_data = array()) {
        global $wpdb;
        
        $data = array(
            'user_id' => $user_id,
            'activity_type' => $activity_type,
            'object_id' => $object_id,
            'object_type' => $object_type,
            'activity_data' => json_encode($extra_data),
            'ip_address' => $this->get_user_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'astmed_user_activities',
            $data,
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Kullanıcı IP adresini al
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     * Rolleri kaldır (uninstall için)
     */
    public static function remove_roles() {
        remove_role('astmed_student');
        remove_role('astmed_instructor');
        remove_role('astmed_admin');
        remove_role('astmed_corporate_admin');
        
        // Mevcut rollerden LMS yetkilerini kaldır
        $roles = array('administrator', 'editor');
        $lms_caps = array(
            'astmed_access_courses',
            'astmed_create_courses',
            'astmed_edit_own_courses',
            'astmed_manage_all_courses',
            'astmed_create_lessons',
            'astmed_edit_own_lessons',
            'astmed_manage_all_lessons',
            'astmed_create_quizzes',
            'astmed_edit_own_quizzes',
            'astmed_manage_all_quizzes',
            'astmed_manage_users',
            'astmed_manage_enrollments',
            'astmed_manage_subscriptions',
            'astmed_manage_payments',
            'astmed_view_all_reports',
            'astmed_export_data',
            'astmed_system_settings',
            'astmed_manage_certificates',
        );
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($lms_caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
        
        delete_option('astmed_lms_roles_created');
        astmed_lms_log('LMS roles removed');
    }
    
    /**
     * Kullanıcının LMS rolünü al
     */
    public static function get_user_lms_role($user_id) {
        $user = new WP_User($user_id);
        
        $lms_roles = array('astmed_admin', 'astmed_instructor', 'astmed_corporate_admin', 'astmed_student');
        
        foreach ($lms_roles as $role) {
            if (in_array($role, $user->roles)) {
                return $role;
            }
        }
        
        return false;
    }
    
    /**
     * Kullanıcı LMS kullanıcısı mı?
     */
    public static function is_lms_user($user_id) {
        return self::get_user_lms_role($user_id) !== false;
    }
}