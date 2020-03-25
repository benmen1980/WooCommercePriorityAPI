<?php

defined('ABSPATH') or die('No direct script access!');

if(isset($_GET['ord'])){
	?>
	<h1>Priority API, sync order to Priority</h1>
	<?php
	$order_id =  $_GET['ord'];
	$response =  $this->syncOrder($order_id,'true');

	echo '<h2>'.__('Request','woocommerce').'</h2>';
	print_r($response['args']['body']);

	echo '<h2>'.__('Response','woocommerce').'</h2>';
	print_r($response['body']);

	echo '<br><br><br><h2>'.__('Refresh this page to re post again','woocommerce').'</h2>';

	echo '<br><br><br><a href="edit.php?post_type=shop_order">'.__('Back to order list','woocommerce').'</a>';



}else {
	wp_die('You got her by mistake ?');
}
