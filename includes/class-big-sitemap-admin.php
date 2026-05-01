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
            'Big SEO Sitemap',
            'Big SEO Sitemap',
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
        $overrides = json_decode(stripslashes($_POST['overrides']??'[]'), true);
        update_option('big_sitemap_url_overrides', $overrides, false);
        Big_Sitemap_Generator::generate();
        wp_send_json_success(['message' => 'Saved and regenerated']);
    }

    public static function ajax_save_xml() {
        check_ajax_referer('big_sitemap_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        $xml = stripslashes($_POST['xml']??'');
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
        
        $tab = $_GET['tab'] ?? 'dashboard';
        ?>
        <div class="wrap big-sitemap-wrap">
            <h1>Big SEO Sitemap</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=big-sitemap&tab=dashboard" class="nav-tab <?= $tab==='dashboard'?'nav-tab-active':'' ?>">Dashboard</a>
                <a href="?page=big-sitemap&tab=view" class="nav-tab <?= $tab==='view'?'nav-tab-active':'' ?>">View &amp; Edit</a>
                <a href="?page=big-sitemap&tab=xml" class="nav-tab <?= $tab==='xml'?'nav-tab-active':'' ?>">Raw XML</a>
                <a href="?page=big-sitemap&tab=settings" class="nav-tab <?= $tab==='settings'?'nav-tab-active':'' ?>">Settings</a>
            </nav>

            <?php if ($tab === 'dashboard'): ?>
                <div class="big-sitemap-section">
                    <div class="big-sitemap-stats">
                        <div class="stat-box">
                            <div class="stat-value"><?= count($urls) ?></div>
                            <div class="stat-label">Total URLs</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?= esc_html($last_updated) ?></div>
                            <div class="stat-label">Last Updated</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?= esc_html($last_pinged) ?></div>
                            <div class="stat-label">Last Pinged</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?= $next_cron ? esc_html(date('Y-m-d H:i', $next_cron)) : 'Not scheduled' ?></div>
                            <div class="stat-label">Next Auto Update</div>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button id="big-sitemap-generate" class="button button-primary button-hero">Generate Sitemap Now</button>
                        <a href="<?= esc_url($sitemap_url) ?>" target="_blank" class="button button-hero">View sitemap.xml</a>
                    </div>

                    <div id="big-sitemap-message" class="notice" style="display:none"></div>

                    <h2>URL Breakdown by Group</h2>
                    <table class="widefat">
                        <thead><tr><th>Group</th><th>URLs</th></tr></thead>
                        <tbody>
                            <?php
                            $groups = [];
                            foreach ($urls as $u) {
                                $g = $u['group'] ?? 'Other';
                                $groups[$g] = ($groups[$g] ?? 0) + 1;
                            }
                            foreach ($groups as $g => $cnt) {
                                echo '<tr><td>'.esc_html($g).'</td><td>'.esc_html($cnt).'</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($tab === 'view'): ?>
                <div class="big-sitemap-section">
                    <p>Edit individual URL settings below. Changes apply on next sitemap generation or manual trigger.</p>
                    <table class="widefat" id="big-sitemap-url-table">
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th>Group</th>
                                <th>Priority</th>
                                <th>Change Freq</th>
                                <th>Last Modified</th>
                                <th>Exclude</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($urls as $idx => $u): ?>
                            <tr data-loc="<?= esc_attr($u['loc']) ?>">
                                <td><a href="<?= esc_url($u['loc']) ?>" target="_blank"><?= esc_html($u['loc']) ?></a></td>
                                <td><?= esc_html($u['group']??'') ?></td>
                                <td>
                                    <select class="priority-select" name="priority">
                                        <?php for ($p=0; $p<=10; $p++): $val = number_format($p/10, 1); ?>
                                        <option value="<?= $val ?>" <?= selected($u['priority'], $val, false) ?>><?= $val ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </td>
                                <td>
                                    <select class="changefreq-select" name="changefreq">
                                        <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $f): ?>
                                        <option value="<?= $f ?>" <?= selected($u['changefreq'], $f, false) ?>><?= ucfirst($f) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><?= esc_html($u['lastmod']??'') ?></td>
                                <td><input type="checkbox" class="exclude-check" /></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button id="save-url-overrides" class="button button-primary">Save Changes &amp; Regenerate</button>
                </div>

            <?php elseif ($tab === 'xml'): ?>
                <div class="big-sitemap-section">
                    <p>Edit the raw XML directly. Click Save to write to /sitemap.xml.</p>
                    <textarea id="big-sitemap-xml-editor" rows="30" style="width:100%;font-family:monospace;"><?php
                        echo file_exists(ABSPATH.'sitemap.xml') ? esc_textarea(file_get_contents(ABSPATH.'sitemap.xml')) : '';
                    ?></textarea>
                    <button id="save-xml-raw" class="button button-primary">Save XML to sitemap.xml</button>
                </div>

            <?php elseif ($tab === 'settings'): ?>
                <form method="post" action="options.php">
                    <?php settings_fields('big_sitemap_settings_group'); ?>
                    <table class="form-table">
                        <tr>
                            <th>Content Types to Include</th>
                            <td>
                                <?php foreach (['post','page','category','cpt','tag','author','product'] as $t): ?>
                                <label><input type="checkbox" name="big_sitemap_settings[content_types][]" value="<?= $t ?>" <?= checked(in_array($t, $settings['content_types']??[]), true, false) ?> /> <?= ucfirst($t) ?></label><br/>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Schedule Mode</th>
                            <td>
                                <label><input type="radio" name="big_sitemap_settings[schedule_mode]" value="rolling" <?= checked($settings['schedule_mode'], 'rolling', false) ?> /> Rolling (24h from last run)</label><br/>
                                <label><input type="radio" name="big_sitemap_settings[schedule_mode]" value="fixed" <?= checked($settings['schedule_mode'], 'fixed', false) ?> /> Fixed Time Daily</label>
                            </td>
                        </tr>
                        <tr>
                            <th>Fixed Time (if selected)</th>
                            <td><input type="time" name="big_sitemap_settings[schedule_time]" value="<?= esc_attr($settings['schedule_time']??'00:00') ?>" /></td>
                        </tr>
                        <tr><th colspan="2"><h3>Default Priority &amp; Change Frequency per Type</h3></th></tr>
                        <?php foreach (['post','page','category','tag','author','cpt','product'] as $t): 
                            $td = $settings['type_defaults'][$t] ?? ['priority'=>'0.5','changefreq'=>'monthly'];
                        ?>
                        <tr>
                            <th><?= ucfirst($t) ?></th>
                            <td>
                                Priority: <select name="big_sitemap_settings[type_defaults][<?= $t ?>][priority]">
                                    <?php for ($p=0; $p<=10; $p++): $v = number_format($p/10, 1); ?>
                                    <option value="<?= $v ?>" <?= selected($td['priority'], $v, false) ?>><?= $v ?></option>
                                    <?php endfor; ?>
                                </select>
                                &nbsp;&nbsp;
                                Change Freq: <select name="big_sitemap_settings[type_defaults][<?= $t ?>][changefreq]">
                                    <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $f): ?>
                                    <option value="<?= $f ?>" <?= selected($td['changefreq'], $f, false) ?>><?= ucfirst($f) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                    <?php submit_button('Save Settings &amp; Reschedule Cron'); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
}
