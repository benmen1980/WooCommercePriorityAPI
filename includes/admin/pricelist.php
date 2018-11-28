<?php defined('ABSPATH') or die('No direct script access!'); ?>

<div class="wrap">

    <?php include P18AW_ADMIN_DIR . 'header.php'; ?>

    <div class="p18a-page">

    <?php
    
        $list = new PriorityWoocommerceAPI\PriceList();
        $list->prepare_items(); 
        $list->display(); 

    ?>
    </div>

</div>