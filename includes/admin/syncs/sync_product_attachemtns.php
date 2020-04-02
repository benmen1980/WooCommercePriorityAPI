<?php

defined('ABSPATH') or die('No direct script access!');


	?>
	<h1>Priority API, sync attachemnts from Priority</h1>
    <h1>Please wait, downloading files can take a while...</h1>
	<?php
	$response =  $this->sync_product_attachemtns();


	echo '<h2>'.__('Response','woocommerce').'</h2>';
	print_r($response);

	echo '<br><br><br><h2>'.__('Refresh this page to sync again','woocommerce').'</h2>';
