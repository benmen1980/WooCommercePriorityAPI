<?php
defined('ABSPATH') or die('No direct script access!');
function simply_create_message_repost($response){
	$message  = '<h2>Request</h2>';
	$message .= $response['args']['body'].'<br>';
	$message .= '<h2>Response</h2>';
	$message .= $response['body'].'<br>';
	return $message;
}
if(isset($_GET['ord'])){
	?>
	<?php
	$order_id =  $_GET['ord'];
	$message = '';
	// sync order
	if($this->option('post_order_checkout')) {
		$message .= '<h1>Priority API, sync Order to Priority</h1>';
		$response = $this->syncOrder( $order_id );
		$message .= simply_create_message_repost($response);
	}
	// sync OTC
	if($this->option('post_einvoice_checkout')) {
		$message .= '<h1>Priority API, sync Over The Counter Invoice to Priority</h1>';
		$response = $this->syncOverTheCounterInvoice( $order_id );
		$message .= simply_create_message_repost($response);
	}
	// sync ainvoices
	if($this->option('post_ainvoice_checkout')) {
		$message .= '<h1>Priority API, sync Sales Invoice to Priority</h1>';
		$response = $this->syncAinvoice($order_id);
		$message .= simply_create_message_repost($response);
	}
	// sync reciepe
	if($this->option('post_receipt_checkout')) {
		$message .= '<h1>Priority API, sync Receipt to Priority</h1>';
		$response = $this->syncReceipt($order_id);
		$message .= simply_create_message_repost($response);
	}
	// message
	echo $message;
	echo '<br><br><br><h2>'.__('Refresh this page to re post again','woocommerce').'</h2>';
	echo '<br><br><br><a href="edit.php?post_type=shop_order">'.__('Back to order list','woocommerce').'</a>';
}else {
	wp_die('You got her by mistake ?');
}
