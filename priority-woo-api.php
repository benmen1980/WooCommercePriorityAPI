<?php
/**
 * @package     Priority Woocommerce API
 * @author      Roy Ben Menachem <roy@simplyCT.co.il>
 * @copyright   2018 SimplyCT
 *
 * @wordpress-plugin
 * Plugin Name: Priority Woocommerce API
 * Plugin URI: http://simplyCT.co.il
 * Description: Priority Woocommerce API extension
 * Version: 1.37
 * Author: SimplyCT
 * Author URI: http://www.simplyCT.co.il
 * Licence: GPLv2
 * Text Domain: p18w
 * Domain Path: /languages
 *
 */

namespace PriorityWoocommerceAPI;

$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
$plugin_version = $plugin_data['Version'];

// Priority Woocommerce API
define('P18AW_VERSION', $plugin_version);
define('P18AW_SELF', __FILE__);
define('P18AW_URI', plugin_dir_url(__FILE__));
define('P18AW_DIR', plugin_dir_path(__FILE__));
define('P18AW_ASSET_DIR', trailingslashit(P18AW_DIR) . 'assets/');
define('P18AW_ASSET_URL', trailingslashit(P18AW_URI) . 'assets/');
define('P18AW_INCLUDES_DIR', trailingslashit(P18AW_DIR) . 'includes/');
define('P18AW_CLASSES_DIR', trailingslashit(P18AW_DIR) . 'includes/classes/');
define('P18AW_ADMIN_DIR', trailingslashit(P18AW_DIR) . 'includes/admin/');
define('P18AW_FRONT_DIR', trailingslashit(P18AW_DIR) . 'includes/front/');
define('P18AW_FRONT_URL', trailingslashit(P18AW_URI) . 'includes/front/');

// define plugin name and plugin admin url
define('P18AW_PLUGIN_NAME', 'Priority WooCommerce API');
define('P18AW_PLUGIN_ADMIN_URL', sanitize_title(P18AW_PLUGIN_NAME));


register_activation_hook(P18AW_SELF, function () {

    global $wp_rewrite;
    $table = $GLOBALS['wpdb']->prefix . 'p18a_pricelists';

    $sql = "CREATE TABLE $table (
        id  INT AUTO_INCREMENT,
        blog_id INT,
        product_sku VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        price_list_code VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        price_list_name VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        price_list_currency VARCHAR(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, 
        price_list_price DECIMAL(6,2), 
        price_list_quant INT,
        PRIMARY KEY  (id)
    )";

    /* This is used add the endpoint and menu item in woocommerce account menu. */
    add_rewrite_endpoint('obligo', EP_PERMALINK | EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('priority-orders', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('priority-invoices', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('priority-receipt', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('priority-documents', EP_ROOT | EP_PAGES);

    /* When we add a new endpoint we need to flush the rewrite rules otherwise it would return 404 */
    $wp_rewrite->flush_rules(false);

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    /* sites */
    $table = $GLOBALS['wpdb']->prefix . 'p18a_sites';

    $sql = "CREATE TABLE $table (
        id  INT AUTO_INCREMENT,
        blog_id INT,
        sitecode VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        sitedesc VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        customer_number VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        address1 VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        PRIMARY KEY  (id)
    )";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    /* special price product family */
    $table = $GLOBALS['wpdb']->prefix . 'p18a_sync_special_price_product_family';
    $sql = "CREATE TABLE $table (
        id  INT AUTO_INCREMENT,
        blog_id INT,
        custname VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        familyname VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        discounts DECIMAL(6,3),
        PRIMARY KEY  (id)
    )";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    dbDelta($sql);

    /* special price item customer */
    $table = $GLOBALS['wpdb']->prefix . 'p18a_special_price_item_customer';
    $sql = "CREATE TABLE $table (
        id  INT AUTO_INCREMENT,
        blog_id INT,
        custname VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        partname VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        price DECIMAL(6,3),
        PRIMARY KEY  (id)
    )";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);


});

// housekeeping
register_deactivation_hook(P18AW_SELF, function () {

     $GLOBALS['wpdb']->query('DROP TABLE IF EXISTS ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists;');

});
// hook up
add_action('plugins_loaded', function () {
    if (is_multisite()) {
        $blog_id = \get_current_blog_id();
        $plugins = \get_blog_option($blog_id, 'active_plugins');
    } else {
        $plugins = get_option('active_plugins');
    }

    // check for PriorityAPI
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    if (is_plugin_active('PriorityAPI/priority18-api.php')) {

        // and check for Woocommerce
        if (is_plugin_active('woocommerce/woocommerce.php')) {

            load_plugin_textdomain('p18w', FALSE, basename(dirname(__FILE__)) . '/languages/');

            require P18AW_CLASSES_DIR . 'wooapi.php';

            WooAPI::instance()->run();
            // load simplypay
            $config = json_decode(stripslashes(WooAPI::instance()->option('setting-config')));
            $simplypay = (!empty($config->simplypay) ? ($config->simplypay == 'true') : false);
            if ($simplypay) {
                require P18AW_FRONT_DIR . 'simplypay/simplypay.php';
                \simplypay::instance()->run();
            }
            // load obligo
            $obligo = WooAPI::instance()->option('obligo') == true;
            if ($obligo) {
                require P18AW_FRONT_DIR . 'my-account/obligo.php';
                \obligo::instance()->run();
                //load prority orders excel
                require P18AW_CLASSES_DIR . 'priority_excel_reports/priority_orders_excel.php';
                \priority_orders_excel::instance()->run();

                //load prority invoices
                require P18AW_CLASSES_DIR . 'priority_invoices/priority_invoices.php';
                \priority_invoices::instance()->run();

                //load prority receipt
                require P18AW_CLASSES_DIR . 'priority_receipt/priority_receipt.php';
                \priority_receipt::instance()->run();

                //load prority document
                require P18AW_CLASSES_DIR . 'priority_documents/priority_documents.php';
                \priority_documents::instance()->run();

                //load prority documents(return from customer)
                require P18AW_CLASSES_DIR . 'priority_delivery_customer/priority_delivery_customer.php';
                \priority_delivery_customer::instance()->run();


                //load prority documents(return from customer)
                require P18AW_CLASSES_DIR . 'priority_return_customer/priority_return_customer.php';
                \priority_return_customer::instance()->run();

                //load prority central invoices
                require P18AW_CLASSES_DIR . 'priority_cinvoices/priority_cinvoices.php';
                \priority_cinvoices::instance()->run();
            }
            if (WooAPI::instance()->option('packs')) {
                require P18AW_ADMIN_DIR . 'packs.php';
            }
            if (WooAPI::instance()->option('sites')) {
                include_once dirname(__FILE__) . '/includes/front/sites/sites.php';
            }
            require P18AW_ADMIN_DIR . 'family-code.php';


        } else {
            add_action('admin_notices', function () {
                printf('<div class="notice notice-error"><p>%s</p></div>', __('In order to use Priority WooCommerce API extension, WooCommerce must be activated', 'p18a'));
            });
        }

    } else {

        add_action('admin_notices', function () {
            printf('<div class="notice notice-error"><p>%s</p></div>', __('In order to use Priority WooCommerce API extension, Priority 18 API must be activated', 'p18a'));
        });

    }

});

include_once dirname(__FILE__) . '/includes/wc_variation_product.php';

include_once(P18AW_FRONT_DIR . 'selectusers/selectusers.php');
