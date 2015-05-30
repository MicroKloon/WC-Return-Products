<?php
if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly
}

/*
Plugin Name: WC Return products
Plugin URL: http://cleverconsulting.net/
Description: Adds a form to order for return product
Version: 1.0
Author: Paco Castillo
Author URI: http://cleverconsulting.net/
Text Domain: wc_return
Domain Path: languages
*/

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

  add_action('wp_head','wc_return_products_ajaxurl');

  function wc_return_products_ajaxurl() {
    echo '<script type="text/javascript">';
    echo 'var wc_return_ajaxurl = "' , admin_url('admin-ajax.php') , '";';
    echo '</script>';
  }

  add_action( 'init', 'wc_return_form_init' );

  function wc_return_form_init() {
    load_theme_textdomain('wc_return', plugin_dir_path( __FILE__ ) . 'languages');
    wp_enqueue_script( 'wc_return_form', plugins_url( 'assets/wc_return_form.js', __FILE__ ) , array('jquery'), false, true );
  }

  // Add form to each order in user account
  add_action( 'woocommerce_order_details_after_order_table', 'wc_return_form_template', 5 , 1 );

  function wc_return_form_template( $order ) {
    // Get WooCommerce Global
    global $woocommerce;
    $last_day = (get_option( 'wc_return_days' ) == '') ? '' : date('Y-m-d', strtotime($order->post->post_date . ' + '. get_option( 'wc_return_days' ) .' days'));
    if ( ($last_day != '') && ($last_day < date("Y-m-d")) )
      return;

    $products = $order->get_items();
    echo '<a class="button return-form-product" href="#">' . __('Return order','wc_return') . '</a>';
    ?>
    <form id="wc-form-return" action="">
      <label><?php _e('Select products for return','wc_return') ?>
        <select name="products" class="products">
          <?php
          if ( sizeof( $products ) > 0 ) {
            echo '<option value="0">' . __('Select products...','wc_return') . '</option>';
            foreach( $products as $item ) {
              echo '<option value="' . $item['item_meta']['_product_id'][0] . '">' . __($item['name'], 'wc_return') . '</option>';
            }
            echo '<option value="';
            foreach( $products as $item ) {
              echo $item['item_meta']['_product_id'][0] . ',';
            }
            echo '">' . __('All products','wc_return') . '</option>';
          }
          ?>
        </select>
      </label>
      <input type="hidden" name="order" value="<?php echo $order->id; ?>" />
      <input type="hidden" name="customer" value="<?php echo $order->billing_email; ?>" />
      <input type="submit" name="submit" value="<?php _e('Submit','wc_return'); ?>" />
    </form>
    <div class="message"></div>
    <?php
  }

  // Send form AJAX --------------------------
  if (defined('DOING_AJAX') && DOING_AJAX) { 

    add_action( 'wp_ajax_wc_return_form', 'send_wc_return_form' );
    add_action( 'wp_ajax_nopriv_wc_return_form', 'send_wc_return_form' );

    function send_wc_return_form()
    {
      global $woocommerce;
      $customer = $woocommerce->customer;
      $json = array();
      $json['result'] = false;

      // check if selected some product
      if ( $_POST['products'] == 0 ) {
        $json['response'] = __('You must select some product','wc_return');
      }
      else {

        $headers = 'From: '.$_POST['customer']."\r\n".
            'Reply-To: '.$_POST['customer']."\r\n".
            'X-Mailer: PHP/'.phpversion();

        $to = (get_option( 'wc_return_email' ) != '') ? get_option( 'wc_return_email' ) : get_option( 'admin_email' );
        $subject = __('Product return. Order no. ','wc_return') . $_POST['order'];

        $message = 'Client with email [' . $_POST['customer'] . '] want´s return a order with id = ' . $_POST['order'] . '<br><br>';

        $all_products = explode(',', $_POST['products']);
        $message .= '<ul>';
        foreach ($all_products as $prod_id) {
          if ( $prod_id != '' ) {
            $prod = new WC_Product($prod_id);
            $message .= '<li><b>' . $prod->post->post_title . ':</b> ' . $prod->id . '</li>';
          }
        }
        $message .= '</ul>';

        add_filter( 'wp_mail_content_type', 'wc_return_form_set_html_content_type' );
        $send = wp_mail($to, $subject, $message, $headers);
        remove_filter( 'wp_mail_content_type', 'wc_return_form_set_html_content_type' );

        $json['send'] = $send;

        if ($send) {
          $json['response'] = __('Your order return request was send successfully and we contact you soon. Thank you.','wc_return');
          $json['result'] = true;
        } else {
          $json['response'] = __('Has encountered an unexpected error and was not able to send email','wc_return');
        }

      }
      echo json_encode($json);
      die();
    }

    function wc_return_form_set_html_content_type() {
      return 'text/html';
    }
  }

  // Admin menu and page ---------------
  add_action('admin_menu', 'wc_return_form_menu');

  function wc_return_form_menu() {
      add_submenu_page( 'woocommerce', 'WC Return products Options', 'WC Return products', 'manage_options', 'wc-return-form-menu', 'wc_return_form_menu_callback' ); 
  }

  function wc_return_form_menu_callback() {
    if ( !current_user_can( 'manage_options' ) )  {
      wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    else {
      $the_email = get_option( 'wc_return_email' );
      if ( $_POST ) {
        if ( isset( $_POST['wc_return_email'] ) ) {
          $the_email = $_POST['wc_return_email'];
          update_option( 'wc_return_email', $the_email );
        }
        if ( isset( $_POST['wc_return_days'] ) ) {
          $days = $_POST['wc_return_days'];
          update_option( 'wc_return_days', $days );
        }
        echo '<div class="updated message">' . __('Changes saved','wc-return') . '</div>';
      }
      echo '<h3>WC Return Products Options</h3>';
      echo '<form action="" method="post" accept-charset="utf-8">';
      echo '<label>' . __('Enter email to send return orders','wc_return') . '<input name="wc_return_email" type="text" value="' . $the_email . '" /></label><br>';
      echo '<label>' . __('How many days will be active this form after the order is completed?','wc_return') . '<input name="wc_return_days" type="number" value="' . $days . '" /></label><br>';
      echo '<input type="submit" value="' . __('Submit', 'wc_return') . '" />';
      echo '</form>';
    }
  }
}