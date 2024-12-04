<?php
defined('ABSPATH') or die('No direct script access!');
function simply_create_message_repost($response)
{
    $message = '<h2>Request</h2>';
    $message .= $response['args']['body'] . '<br>';
    $message .= '<h2>Response</h2>';
    $message .= $response['message'] . '<br>' . $response['body'] . '<br>';
    return $message;
}

if (isset($_GET['ord'])) {
    ?>
    <?php
    $order_id = $_GET['ord'];
    $message = '';
    // sync customer
    $order = new \WC_Order($order_id);
    $user_id = $order->get_user_id();
    $message .= '<h1>Priority API, sync Customer to Priority</h1>';
    $response = $this->getPriorityCustomer($order);
    $message .= simply_create_message_repost($response);
    // sync order
    if ($this->option('post_order_checkout')) {
        $message .= '<h1>Priority API, sync Order to Priority</h1>';
        $response = $this->syncOrder($order_id);
        $message .= simply_create_message_repost($response);
    }
    // sync shipment
    if ($this->option('post_document_d_checkout')) {
        $message .= '<h1>Priority API, sync Shipping to Priority</h1>';
        $response = $this->syncDocumentD($order_id);
        $message .= simply_create_message_repost($response);
    }
    // sync OTC
    if ($this->option('post_einvoice_checkout')) {
        $message .= '<h1>Priority API, sync Over The Counter Invoice to Priority</h1>';
        $response = $this->syncOverTheCounterInvoice($order_id);
        $message .= simply_create_message_repost($response);
    }
    // sync ainvoices
    if ($this->option('post_ainvoice_checkout')) {
        $message .= '<h1>Priority API, sync Sales Invoice to Priority</h1>';
        $response = $this->syncAinvoice($order_id);
        $message .= simply_create_message_repost($response);
    }
    // sync reciepe
    if ($this->option('post_receipt_checkout')) {
        $message .= '<h1>Priority API, sync Receipt to Priority</h1>';
        $response = $this->syncReceipt($order_id);
        $message .= simply_create_message_repost($response);
    }
    // sync POS
    if ($this->option('post_pos_checkout')) {
        $message .= '<h1>Priority API, sync POS to Priority</h1>';
        $response = $this->syncTransactionPos($order_id);
        $message .= simply_create_message_repost($response);
    }
    if($this->option('cardPos')){
        $message = '';
        require_once WP_PLUGIN_DIR. '/WooCommercePriorityAPI/includes/classes/card_pos/card_pos.php';
        $order = new \WC_Order($order_id);
        //$order_cc_meta = $order->get_meta('_transaction_data');
        $transaction_data = get_post_meta( $order_id, '_transaction_data' );
        $payment_method = get_post_meta($order_id, '_payment_method', true);
        $order_data = $order->get_data();
        $update_request = get_post_meta($order_id, 'request_transaction_update', true);
        $update_result = get_post_meta($order_id, 'response_transaction_update', true);
        $update_result_error = get_post_meta($order_id, 'response_transaction_update_error', true);
        $approve_result = get_post_meta($order_id, 'response_transaction_approve', true);
        $approve_result_error = get_post_meta($order_id, 'response_transaction_approve_error_msg', true);
        $cancel_request = get_post_meta($order_id, 'cancel_request', true);
        $close_response = get_post_meta($order_id, 'response_transaction_close', true);
        // $response = CardPOS::instance()->close_transaction($order_id);
        $response = CardPOS::instance()->temporary_transaction_for_repost($order_id);
        echo '<pre style="direction: ltr;">';
        echo '<h1>Sync CART POS transaction to EDEA</h1><h2>Request update: </h2>';
        print_r(json_encode($update_request,JSON_PRETTY_PRINT));
        echo '</br>';
        echo '<h2>Request Approve: update result</h2>';
        if($update_result_error!= ''){
            echo '<h2>Update result error</h2>';
            print_r($update_result_error);
            echo '</br>';
        }
        else{ 
            print_r(json_encode($update_result,JSON_PRETTY_PRINT));
        }

        echo '</br>';
        if($approve_result_error!= ''){
            echo '<h2>Approve result error</h2>';
            print_r($approve_result_error);
            echo '</br>';
        }
        else{
            echo '<h2>Request close: approve result</h2>';

            //print_r($approve_result);
            print_r($cancel_request);
            echo '</br>';
        }


        echo '<h2>Response close</h2>';
        print_r($close_response);
        //print_r($response);
        echo '</pre>';
    }
    // message
    echo $message;
    echo '<br><br><br><h2>' . __('Refresh this page to re post again', 'woocommerce') . '</h2>';
    echo '<br><br><br><a href="edit.php?post_type=shop_order">' . __('Back to order list', 'woocommerce') . '</a>';
} else {
    wp_die('You got her by mistake ?');
}
