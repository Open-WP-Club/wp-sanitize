<?php

/**
 * Plugin Name: WP Data Sanitizer
 * Description: Sanitizes data for staging environments with various options and batching
 * Version: 2.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class WP_Data_Sanitizer
{
  private static $instance = null;
  private $options;

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
      <h1>WP Data Sanitizer</h1>
      <form method="post" action="options.php">
        <?php
        settings_fields('wp_data_sanitizer_options_group');
        do_settings_sections('wp-data-sanitizer-admin');
        submit_button('Save Settings');
        ?>
      </form>
      <button id="start-sanitization" class="button button-primary">Start Sanitization</button>
      <div id="sanitization-progress" style="display:none;">
        <progress id="sanitization-bar" value="0" max="100"></progress>
        <p id="sanitization-status"></p>
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
      'sanitize_posts',
      'Sanitize Post Content',
      array($this, 'checkbox_callback'),
      'wp-data-sanitizer-admin',
      'wp_data_sanitizer_setting_section',
      array('sanitize_posts')
    );

    add_settings_field(
      'sanitize_comments',
      'Sanitize Comments',
      array($this, 'checkbox_callback'),
      'wp-data-sanitizer-admin',
      'wp_data_sanitizer_setting_section',
      array('sanitize_comments')
    );
  }

  public function sanitize($input)
  {
    $new_input = array();
    $new_input['sanitize_emails'] = isset($input['sanitize_emails']) ? true : false;
    $new_input['sanitize_usernames'] = isset($input['sanitize_usernames']) ? true : false;
    $new_input['sanitize_posts'] = isset($input['sanitize_posts']) ? true : false;
    $new_input['sanitize_comments'] = isset($input['sanitize_comments']) ? true : false;
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
    wp_enqueue_script('wp-data-sanitizer-admin-js', plugins_url('admin.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script('wp-data-sanitizer-admin-js', 'wpDataSanitizer', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('wp_data_sanitizer_nonce')
    ));
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

    if ($options['sanitize_emails']) {
      $total += $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
      $users = $wpdb->get_results("SELECT ID, user_email FROM {$wpdb->users} LIMIT $offset, $batch_size");
      foreach ($users as $user) {
        $sanitized_email = md5($user->user_email) . '@example.com';
        $wpdb->update($wpdb->users, array('user_email' => $sanitized_email), array('ID' => $user->ID));
        $processed++;
      }
    }

    if ($options['sanitize_usernames']) {
      if ($total == 0) $total += $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
      $users = $wpdb->get_results("SELECT ID FROM {$wpdb->users} LIMIT $offset, $batch_size");
      foreach ($users as $user) {
        $wpdb->update(
          $wpdb->users,
          array('user_login' => 'user_' . $user->ID, 'display_name' => 'User ' . $user->ID),
          array('ID' => $user->ID)
        );
        $processed++;
      }
    }

    if ($options['sanitize_posts']) {
      $total += $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");
      $posts = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} LIMIT $offset, $batch_size");
      foreach ($posts as $post) {
        $wpdb->update(
          $wpdb->posts,
          array('post_content' => 'Sanitized content for post ' . $post->ID),
          array('ID' => $post->ID)
        );
        $processed++;
      }
    }

    if ($options['sanitize_comments']) {
      $total += $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}");
      $comments = $wpdb->get_results("SELECT comment_ID FROM {$wpdb->comments} LIMIT $offset, $batch_size");
      foreach ($comments as $comment) {
        $wpdb->update(
          $wpdb->comments,
          array('comment_content' => 'Sanitized comment ' . $comment->comment_ID),
          array('comment_ID' => $comment->comment_ID)
        );
        $processed++;
      }
    }

    $progress = ($offset + $processed) / $total * 100;
    $done = ($offset + $processed) >= $total;

    wp_send_json(array(
      'processed' => $processed,
      'total' => $total,
      'progress' => $progress,
      'done' => $done
    ));
  }
}

$wp_data_sanitizer = WP_Data_Sanitizer::get_instance();
