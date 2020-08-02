<?php

defined('ABSPATH') or die('No direct script access!');

if(isset($_GET['ord'])){
	if($this->option('post_order_checkout')) {
		echo '<h1>Priority API, sync Order to Priority</h1>';
	}elseif($this->option('post_einvoices_checkout')) {
		echo '<h1>Priority API, sync Over The Counter Invoice to Priority</h1>';
	}
	?>

	<?php
	$order_id =  $_GET['ord'];
	// sync order
	if($this->option('post_order_checkout')) {
		$response = $this->syncOrder( $order_id );
	}
	// sync OTC
	if($this->option('post_einvoices_checkout')) {
		$response = $this->syncOverTheCounterInvoice( $order_id );
	}
	if($this->option('sync_onorder_receipts')) {
		// sync receipts
		$response = $this->syncReceipt($order_id);
	}
	echo '<h2>'.__('Request','woocommerce').'</h2>';
	print_r($response['args']['body']);

	echo '<h2>'.__('Response','woocommerce').'</h2>';
	print_r($response['body']);

	echo '<br><br><br><h2>'.__('Refresh this page to re post again','woocommerce').'</h2>';

	echo '<br><br><br><a href="edit.php?post_type=shop_order">'.__('Back to order list','woocommerce').'</a>';



}else {
	wp_die('You got her by mistake ?');
}
