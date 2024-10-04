<?php
/*
 * Plugin Name:             WP Data Sanitizer
 * Plugin URI:              https://github.com/Open-WP-Club/wp-internal-linking
 * Description:             Sanitizes data for staging environments with various options and batching, including WooCommerce orders
 * Version:                 1.1.0
 * Author:                  Open WP Club
 * Author URI:              https://openwpclub.com
 * License:                 GPL-2.0 License
 * Requires Plugins:        
 * Requires at least:       6.0
 * Requires PHP:            7.4
 * Tested up to:            6.6.1
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class WP_Data_Sanitizer
{
  private static $instance = null;
  private $options;
  private $excluded_roles = array('administrator', 'editor', 'author', 'contributor', 'shop_manager');

  public static function get_instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct()
  {
    add_action('admin_menu', array($this, 'add_plugin_page'));
    add_action('admin_init', array($this, 'page_init'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    add_action('wp_ajax_sanitize_batch', array($this, 'sanitize_batch'));
  }

  public function add_plugin_page()
  {
    add_management_page(
      'WP Data Sanitizer',
      'Data Sanitizer',
      'manage_options',
      'wp-data-sanitizer',
      array($this, 'create_admin_page')
    );
  }

  public function create_admin_page()
  {
    $this->options = get_option('wp_data_sanitizer_options');
?>
    <div class="wrap">
      <h1 class="wp-heading-inline">WP Data Sanitizer</h1>
      <hr class="wp-header-end">

      <div class="notice notice-warning">
        <p><strong>Warning:</strong> This plugin will modify your database. Please make sure you have a backup before proceeding.</p>
      </div>

      <div class="card">
        <h2 class="title">Sanitization Options</h2>
        <form method="post" action="options.php">
          <?php
          settings_fields('wp_data_sanitizer_options_group');
          do_settings_sections('wp-data-sanitizer-admin');
          submit_button('Save Settings');
          ?>
        </form>
      </div>

      <div class="card">
        <h2 class="title">Run Sanitization</h2>
        <p>Click the button below to start the sanitization process:</p>
        <button id="start-sanitization" class="button button-primary">Start Sanitization</button>
        <div id="sanitization-progress" style="display:none; margin-top: 20px;">
          <div class="progress-bar-wrapper">
            <div id="sanitization-bar"></div>
          </div>
          <p id="sanitization-status"></p>
        </div>
      </div>
    </div>
<?php
  }

  public function page_init()
  {
    register_setting(
      'wp_data_sanitizer_options_group',
      'wp_data_sanitizer_options',
      array($this, 'sanitize')
    );

    add_settings_section(
      'wp_data_sanitizer_setting_section',
      'Sanitization Options',
      array($this, 'section_info'),
      'wp-data-sanitizer-admin'
    );

    add_settings_field(
      'sanitize_emails',
      'Sanitize Emails',
      array($this, 'checkbox_callback'),
      'wp-data-sanitizer-admin',
      'wp_data_sanitizer_setting_section',
      array('sanitize_emails')
    );

    add_settings_field(
      'sanitize_usernames',
      'Sanitize Usernames',
      array($this, 'checkbox_callback'),
      'wp-data-sanitizer-admin',
      'wp_data_sanitizer_setting_section',
      array('sanitize_usernames')
    );

    add_settings_field(
      'sanitize_comments',
      'Sanitize Comments',
      array($this, 'checkbox_callback'),
      'wp-data-sanitizer-admin',
      'wp_data_sanitizer_setting_section',
      array('sanitize_comments')
    );

    if ($this->is_woocommerce_active()) {
      add_settings_field(
        'sanitize_wc_orders',
        'Sanitize WooCommerce Orders',
        array($this, 'checkbox_callback'),
        'wp-data-sanitizer-admin',
        'wp_data_sanitizer_setting_section',
        array('sanitize_wc_orders')
      );
    }
  }

  public function sanitize($input)
  {
    $new_input = array();
    $new_input['sanitize_emails'] = isset($input['sanitize_emails']) ? true : false;
    $new_input['sanitize_usernames'] = isset($input['sanitize_usernames']) ? true : false;
    $new_input['sanitize_comments'] = isset($input['sanitize_comments']) ? true : false;
    $new_input['sanitize_wc_orders'] = isset($input['sanitize_wc_orders']) ? true : false;
    return $new_input;
  }

  public function section_info()
  {
    echo 'Choose which data to sanitize:';
  }

  public function checkbox_callback($args)
  {
    $option = $args[0];
    $checked = isset($this->options[$option]) ? checked($this->options[$option], true, false) : '';
    echo '<input type="checkbox" id="' . $option . '" name="wp_data_sanitizer_options[' . $option . ']" value="1" ' . $checked . '/>';
  }

  public function enqueue_admin_scripts($hook)
  {
    if ('tools_page_wp-data-sanitizer' !== $hook) {
      return;
    }
    wp_enqueue_script('wp-data-sanitizer-admin-js', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '1.1.0', true);
    wp_localize_script('wp-data-sanitizer-admin-js', 'wpDataSanitizer', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('wp_data_sanitizer_nonce')
    ));
    wp_enqueue_style('wp-data-sanitizer-admin-css', plugins_url('assets/css/admin.css', __FILE__), array(), '1.1.0');
  }

  public function sanitize_batch()
  {
    check_ajax_referer('wp_data_sanitizer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_die('Unauthorized');
    }

    global $wpdb;
    $options = get_option('wp_data_sanitizer_options');
    $batch_size = 100;
    $offset = intval($_POST['offset']);

    $total = 0;
    $processed = 0;
    $error_log = array();

    try {
      if ($options['sanitize_emails'] || $options['sanitize_usernames']) {
        $total += $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        $users = $wpdb->get_results("SELECT ID, user_email FROM {$wpdb->users} LIMIT $offset, $batch_size");
        foreach ($users as $user) {
          $user_obj = new WP_User($user->ID);
          $user_roles = $user_obj->roles;

          $should_sanitize = !array_intersect($user_roles, $this->excluded_roles);

          if ($should_sanitize) {
            if ($options['sanitize_emails']) {
              $sanitized_email = md5($user->user_email) . '@example.com';
              $result = $wpdb->update($wpdb->users, array('user_email' => $sanitized_email), array('ID' => $user->ID));
              if ($result === false) {
                $error_log[] = "Failed to update email for user ID: " . $user->ID;
              }
            }

            if ($options['sanitize_usernames']) {
              $result = $wpdb->update(
                $wpdb->users,
                array('user_login' => 'user_' . $user->ID, 'display_name' => 'User ' . $user->ID),
                array('ID' => $user->ID)
              );
              if ($result === false) {
                $error_log[] = "Failed to update username for user ID: " . $user->ID;
              }
            }
          } else {
            $error_log[] = "Skipped sanitization for user ID: " . $user->ID . " (excluded role)";
          }

          $processed++;
        }
      }

      if ($options['sanitize_comments']) {
        $total += $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}");
        $comments = $wpdb->get_results("SELECT comment_ID FROM {$wpdb->comments} LIMIT $offset, $batch_size");
        foreach ($comments as $comment) {
          $result = $wpdb->update(
            $wpdb->comments,
            array('comment_content' => 'Sanitized comment ' . $comment->comment_ID),
            array('comment_ID' => $comment->comment_ID)
          );
          if ($result === false) {
            $error_log[] = "Failed to update content for comment ID: " . $comment->comment_ID;
          }
          $processed++;
        }
      }

      if ($options['sanitize_wc_orders'] && $this->is_woocommerce_active()) {
        $total += $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders");
        $orders = $wpdb->get_results("SELECT id FROM {$wpdb->prefix}wc_orders LIMIT $offset, $batch_size");
        foreach ($orders as $order) {
          $this->sanitize_wc_order($order->id);
          $processed++;
        }
      }

      $progress = ($offset + $processed) / $total * 100;
      $done = ($offset + $processed) >= $total;

      wp_send_json(array(
        'processed' => $processed,
        'total' => $total,
        'progress' => $progress,
        'done' => $done,
        'error_log' => $error_log,
        'last_offset' => $offset,
        'next_offset' => $offset + $processed
      ));
    } catch (Exception $e) {
      wp_send_json_error(array(
        'message' => $e->getMessage(),
        'last_offset' => $offset,
        'processed' => $processed,
        'error_log' => $error_log
      ));
    }
  }

  private function sanitize_wc_order($order_id)
  {
    $order = wc_get_order($order_id);
    if (!$order) {
      return;
    }

    // Sanitize billing information
    $order->set_billing_first_name('John');
    $order->set_billing_last_name('Doe');
    $order->set_billing_company('Example Company');
    $order->set_billing_address_1('123 Main St');
    $order->set_billing_address_2('');
    $order->set_billing_city('Anytown');
    $order->set_billing_state('CA');
    $order->set_billing_postcode('12345');
    $order->set_billing_country('US');
    $order->set_billing_email('john.doe' . $order_id . '@example.com');
    $order->set_billing_phone('555-123-4567');

    // Sanitize shipping information
    $order->set_shipping_first_name('Jane');
    $order->set_shipping_last_name('Doe');
    $order->set_shipping_company('Example Company');
    $order->set_shipping_address_1('456 Elm St');
    $order->set_shipping_address_2('');
    $order->set_shipping_city('Othertown');
    $order->set_shipping_state('NY');
    $order->set_shipping_postcode('67890');
    $order->set_shipping_country('US');

    $order->save();
  }

  private function is_woocommerce_active()
  {
    return class_exists('WooCommerce');
  }
}

$wp_data_sanitizer = WP_Data_Sanitizer::get_instance();
