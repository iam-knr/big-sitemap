<?php
if (!defined('ABSPATH')) exit;

class Big_Sitemap_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_ajax_big_sitemap_generate_now', [__CLASS__, 'ajax_generate']);
        add_action('wp_ajax_big_sitemap_save_overrides', [__CLASS__, 'ajax_save_overrides']);
        add_action('wp_ajax_big_sitemap_save_xml', [__CLASS__, 'ajax_save_xml']);
    }

    public static function menu() {
        add_menu_page(
            'Big Sitemap',
            'Big Sitemap',
            'manage_options',
            'big-sitemap',
            [__CLASS__, 'page'],
            'dashicons-networking',
            30
        );
    }

    public static function assets($hook) {
        if ($hook !== 'toplevel_page_big-sitemap') return;
        wp_enqueue_style('big-sitemap-admin', BIG_SITEMAP_URL.'assets/css/admin.css', [], BIG_SITEMAP_VERSION);
        wp_enqueue_script('big-sitemap-admin', BIG_SITEMAP_URL.'assets/js/admin.js', ['jquery'], BIG_SITEMAP_VERSION, true);
        wp_localize_script('big-sitemap-admin', 'bigSitemapAjax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('big_sitemap_nonce'),
        ]);
    }

    public static function ajax_generate() {
        check_ajax_referer('big_sitemap_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        $count = Big_Sitemap_Generator::generate();
        wp_send_json_success(['message' => "Sitemap regenerated: $count URLs", 'count' => $count]);
    }

    public static function ajax_save_overrides() {
        check_ajax_referer('big_sitemap_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        $raw_overrides = isset($_POST['overrides']) ? wp_unslash($_POST['overrides']) : '';
        $overrides = json_decode(stripslashes($raw_overrides ?: '[]'), true);
        if (is_array($overrides)) {
            $overrides = array_map('sanitize_text_field', (array) $overrides);
        }
        update_option('big_sitemap_url_overrides', $overrides, false);
        Big_Sitemap_Generator::generate();
        wp_send_json_success(['message' => 'Saved and regenerated']);
    }

    public static function ajax_save_xml() {
        check_ajax_referer('big_sitemap_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        $raw_xml = isset($_POST['xml']) ? wp_unslash($_POST['xml']) : '';
        $xml = stripslashes($raw_xml);
        $xml = sanitize_textarea_field($xml);
        file_put_contents(ABSPATH.'sitemap.xml', $xml);
        wp_send_json_success(['message' => 'XML saved to sitemap.xml']);
    }

    public static function page() {
        $settings = Big_Sitemap_Settings::get();
        $urls = get_option('big_sitemap_urls',[]);
        $last_updated = get_option('big_sitemap_last_updated', 'Never');
        $last_pinged = get_option('big_sitemap_last_pinged', 'Never');
        $overrides = get_option('big_sitemap_url_overrides',[]);
        $next_cron = wp_next_scheduled('big_sitemap_cron_event');
        $sitemap_url = home_url('/sitemap.xml');

        // Sanitize tab parameter
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'dashboard';
        $allowed_tabs = ['dashboard', 'view', 'xml', 'settings'];
        if (!in_array($tab, $allowed_tabs, true)) $tab = 'dashboard';
?>
<div class="wrap big-sitemap-wrap">
    <h1>Big Sitemap</h1>

    <nav class="nav-tab-wrapper">
        <a href="?page=big-sitemap&tab=dashboard" class="nav-tab <?php echo $tab==='dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
        <a href="?page=big-sitemap&tab=view" class="nav-tab <?php echo $tab==='view' ? 'nav-tab-active' : ''; ?>">View & Edit</a>
        <a href="?page=big-sitemap&tab=xml" class="nav-tab <?php echo $tab==='xml' ? 'nav-tab-active' : ''; ?>">Raw XML</a>
        <a href="?page=big-sitemap&tab=settings" class="nav-tab <?php echo $tab==='settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
    </nav>

    <?php if ($tab === 'dashboard'): ?>
    <div class="big-sitemap-section">
        <!-- Dashboard stats, buttons, and URL breakdown table -->
    <?php elseif ($tab === 'view'): ?>
    <!-- URL editing table -->
    <?php elseif ($tab === 'xml'): ?>
    <!-- Raw XML editor -->
    <?php elseif ($tab === 'settings'): ?>
    <!-- Settings form -->
    <?php endif; ?>
</div>
<?php
    }
}
