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
		$response_order = $this->syncOrder( $order_id );
	}
	// sync OTC
	if($this->option('post_einvoices_checkout')) {
		$response_otc = $this->syncOverTheCounterInvoice( $order_id );
	}
	// sync ainvoices
	if($this->option('sync_onorder_ainvoices')) {
		$response_aiv = $this->syncAinvoice($order_id);
	}
	// sync reciepe
	if($this->option('sync_onorder_receipts')) {
		$response_rec = $this->syncReceipt($order_id);
	}

	echo '<h2>'.__('Request','woocommerce').'</h2>';
	print_r($response_order['args']['body']);
	echo '<br><br>';
	print_r($response_otc['args']['body']);
	echo '<br><br>';
	print_r($response_aiv['args']['body']);
	echo '<br><br>';
	print_r($response_rec['args']['body']);



	echo '<h2>'.__('Response','woocommerce').'</h2>';
	print_r($response_order['body']);
	echo '<br><br>';
	print_r($response_otc['body']);
	echo '<br><br>';
	print_r($response_aiv['body']);
	echo '<br><br>';
	print_r($response_rec['body']);
	echo '<br><br>';

	echo '<br><br><br><h2>'.__('Refresh this page to re post again','woocommerce').'</h2>';

	echo '<br><br><br><a href="edit.php?post_type=shop_order">'.__('Back to order list','woocommerce').'</a>';



}else {
	wp_die('You got her by mistake ?');
}
