<?php
/**
 * Plugin Name: Playground Welcome
 * Description: A welcome dialog for WordPress Playground that lets you set your name and import RSS feed content.
 * Version: 1.0.0
 * Author: Playground
 * License: GPL-2.0+
 * Text Domain: playground-welcome
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

class Playground_Welcome {

    private $option_name = 'playground_welcome_completed';

    public function __construct() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('wp_ajax_playground_welcome_save', [$this, 'handle_save']);
    }

    public function load_textdomain() {
        load_plugin_textdomain('playground-welcome', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public static function get_welcome_post_path() {
        $locale = determine_locale();
        $lang = substr($locale, 0, 2);
        $plugin_dir = plugin_dir_path(__FILE__);

        $localized_file = $plugin_dir . $lang . '/welcome-post.html';
        if (file_exists($localized_file)) {
            return $localized_file;
        }

        return $plugin_dir . 'welcome-post.html';
    }

    public static function get_welcome_post_content() {
        $path = self::get_welcome_post_path();
        if (file_exists($path)) {
            return file_get_contents($path);
        }
        return '';
    }

    public static function activate() {
        $post = get_post(1);
        if (!$post) {
            return;
        }

        add_filter('wp_kses_allowed_html', [__CLASS__, 'allow_svg_tags']);

        wp_update_post([
            'ID' => $post->ID,
            'post_title' => __('Welcome to Your WordPress', 'playground-welcome'),
            'post_content' => self::get_welcome_post_content(),
            'post_name' => 'welcome-to-your-wordpress',
        ]);
    }

    public static function allow_svg_tags($tags) {
        $tags['svg'] = [
            'viewbox' => true,
            'xmlns' => true,
            'width' => true,
            'height' => true,
            'aria-hidden' => true,
            'focusable' => true,
            'style' => true,
        ];
        $tags['path'] = [
            'd' => true,
            'fill-rule' => true,
            'clip-rule' => true,
        ];
        return $tags;
    }

    public function add_admin_page() {
        add_menu_page(
            __('Welcome', 'playground-welcome'),
            __('Welcome', 'playground-welcome'),
            'manage_options',
            'playground-welcome',
            [$this, 'render_page'],
            'dashicons-welcome-learn-more',
            2
        );
    }

    public function enqueue_styles($hook) {
        if ($hook !== 'toplevel_page_playground-welcome') {
            return;
        }

        wp_enqueue_style(
            'playground-welcome',
            plugin_dir_url(__FILE__) . 'playground-welcome.css',
            [],
            '1.0.0'
        );
    }

    public function render_page() {
        $current_user = wp_get_current_user();
        ?>
        <div class="playground-welcome-overlay">
            <div class="playground-welcome-dialog">
                <h1><?php echo esc_html__('👋 Welcome to Your WordPress', 'playground-welcome'); ?></h1>
                <p class="intro"><?php echo esc_html__("This is a private WordPress that's free and needs no account. It's stored in your browser and will be here when you come back.", 'playground-welcome'); ?></p>

                <form id="playground-welcome-form" method="post">
                    <?php wp_nonce_field('playground_welcome_nonce', 'nonce'); ?>

                    <div class="field-group">
                        <label for="display_name"><?php echo esc_html__("What's your name?", 'playground-welcome'); ?></label>
                        <input
                            type="text"
                            id="display_name"
                            name="display_name"
                            placeholder="<?php echo esc_attr($current_user->display_name); ?>"
                            autofocus
                        >
                    </div>

                    <details class="import-details">
                        <summary><?php echo esc_html__('Import content from a website', 'playground-welcome'); ?></summary>
                        <div class="field-group">
                            <label for="feed_url"><?php echo esc_html__('Website URL', 'playground-welcome'); ?></label>
                            <input
                                type="text"
                                id="feed_url"
                                name="feed_url"
                                placeholder="example.com"
                            >
                            <p class="field-hint"><?php echo esc_html__("Enter a site URL and we'll find and import its RSS feed.", 'playground-welcome'); ?></p>
                        </div>

                        <div class="field-group">
                            <label for="max_items"><?php echo esc_html__('Maximum posts to import', 'playground-welcome'); ?></label>
                            <select id="max_items" name="max_items">
                                <option value="5"><?php echo esc_html__('5 posts', 'playground-welcome'); ?></option>
                                <option value="10" selected><?php echo esc_html__('10 posts', 'playground-welcome'); ?></option>
                                <option value="20"><?php echo esc_html__('20 posts', 'playground-welcome'); ?></option>
                                <option value="50"><?php echo esc_html__('50 posts', 'playground-welcome'); ?></option>
                            </select>
                        </div>
                    </details>

                    <div id="welcome-message" class="welcome-message" style="display: none;"></div>

                    <div class="button-group">
                        <button type="submit" class="button-primary" id="save-button">
                            <span class="button-text"><?php echo esc_html__('Get Started', 'playground-welcome'); ?></span>
                            <span class="button-loading" style="display: none;"><?php echo esc_html__('Importing...', 'playground-welcome'); ?></span>
                        </button>
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="button-secondary"><?php echo esc_html__('Skip & Go to Site', 'playground-welcome'); ?></a>
                    </div>
                </form>
            </div>
        </div>

        <script>
        document.getElementById('playground-welcome-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = this;
            const button = document.getElementById('save-button');
            const buttonText = button.querySelector('.button-text');
            const buttonLoading = button.querySelector('.button-loading');
            const messageEl = document.getElementById('welcome-message');

            button.disabled = true;
            buttonText.style.display = 'none';
            buttonLoading.style.display = 'inline';
            messageEl.style.display = 'none';

            const formData = new FormData(form);
            formData.append('action', 'playground_welcome_save');

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageEl.className = 'welcome-message success';
                    messageEl.textContent = data.data.message;
                    messageEl.style.display = 'block';

                    setTimeout(() => {
                        window.location.href = '<?php echo esc_url(home_url('/')); ?>';
                    }, 1500);
                } else {
                    messageEl.className = 'welcome-message error';
                    messageEl.textContent = data.data.message || 'An error occurred.';
                    messageEl.style.display = 'block';

                    button.disabled = false;
                    buttonText.style.display = 'inline';
                    buttonLoading.style.display = 'none';
                }
            })
            .catch(error => {
                messageEl.className = 'welcome-message error';
                messageEl.textContent = 'An error occurred. Please try again.';
                messageEl.style.display = 'block';

                button.disabled = false;
                buttonText.style.display = 'inline';
                buttonLoading.style.display = 'none';
            });
        });
        </script>
        <?php
    }

    public function handle_save() {
        if (!wp_verify_nonce($_POST['nonce'], 'playground_welcome_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'playground-welcome')]);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'playground-welcome')]);
        }

        $messages = [];

        $display_name = sanitize_text_field($_POST['display_name'] ?? '');
        if (!empty($display_name)) {
            $user_id = get_current_user_id();
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $display_name,
            ]);
            /* translators: %s: user's display name */
            update_option('blogname', sprintf(__("%s's Playground", 'playground-welcome'), $display_name));
            /* translators: %s: user's display name */
            $messages[] = sprintf(__('Name updated to "%s"', 'playground-welcome'), $display_name);
        }

        $feed_url = trim($_POST['feed_url'] ?? '');
        $max_items = intval($_POST['max_items'] ?? 10);

        if (!empty($feed_url)) {
            if (!preg_match('~^https?://~i', $feed_url)) {
                $feed_url = 'https://' . $feed_url;
            }
            $feed_url = esc_url_raw($feed_url);
            $import_result = $this->import_feed($feed_url, $max_items);
            if ($import_result['success']) {
                $messages[] = $import_result['message'];
            } else {
                wp_send_json_error(['message' => $import_result['message']]);
            }
        }

        update_option($this->option_name, true);

        $final_message = !empty($messages)
            ? implode('. ', $messages) . '. ' . __('Redirecting to your site...', 'playground-welcome')
            : __('Setup complete! Redirecting to your site...', 'playground-welcome');

        wp_send_json_success(['message' => $final_message]);
    }

    private function import_feed($feed_url, $max_items) {
        include_once ABSPATH . WPINC . '/feed.php';

        $feed = fetch_feed($feed_url);

        if (is_wp_error($feed)) {
            return [
                'success' => false,
                /* translators: %s: error message */
                'message' => sprintf(__('Could not fetch feed: %s', 'playground-welcome'), $feed->get_error_message())
            ];
        }

        $items = $feed->get_items(0, $max_items);

        if (empty($items)) {
            return [
                'success' => false,
                'message' => __('No items found in the feed.', 'playground-welcome')
            ];
        }

        $imported = 0;
        $current_user_id = get_current_user_id();

        foreach ($items as $item) {
            $title = $item->get_title();
            $content = $item->get_content();
            $date = $item->get_date('Y-m-d H:i:s');
            $permalink = $item->get_permalink();

            $existing = get_posts([
                'post_type' => 'post',
                'meta_key' => '_playground_feed_source',
                'meta_value' => $permalink,
                'posts_per_page' => 1
            ]);

            if (!empty($existing)) {
                continue;
            }

            /* translators: %s: URL of the original article */
            $content .= "\n\n<p><em>" . sprintf(__('Originally published at <a href="%1$s">%2$s</a>', 'playground-welcome'), esc_url($permalink), esc_html(parse_url($permalink, PHP_URL_HOST))) . "</em></p>";

            $post_id = wp_insert_post([
                'post_title' => sanitize_text_field($title),
                'post_content' => wp_kses_post($content),
                'post_status' => 'publish',
                'post_author' => $current_user_id,
                'post_date' => $date ?: current_time('mysql'),
            ]);

            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, '_playground_feed_source', $permalink);
                $imported++;
            }
        }

        return [
            'success' => true,
            /* translators: %d: number of imported posts */
            'message' => sprintf(_n('Imported %d post from feed', 'Imported %d posts from feed', $imported, 'playground-welcome'), $imported)
        ];
    }
}

register_activation_hook(__FILE__, ['Playground_Welcome', 'activate']);
new Playground_Welcome();
