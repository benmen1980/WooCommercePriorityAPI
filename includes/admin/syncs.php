<?php defined('ABSPATH') or die('No direct script access!');
$format = 'm/d/Y H:i:s';
$format2 = 'd/m/Y H:i:s';
?>

<form id="p18aw-sync" name="p18aw-sync" method="post"
      action="<?php echo admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL . '&tab=syncs'); ?>">
    <?php wp_nonce_field('save-sync', 'p18aw-nonce'); ?>
</form>
<div class="wrap">

    <?php include P18AW_ADMIN_DIR . 'header.php'; ?>

    <div class="p18a-page-wrapper api-sync">

        <br><br>
        <table class="p18a" style="max-width: 300px;" cellspacing="20">

            <tr>
                <td><strong>
                        <div style="width:200px"><?php _e('Sync', 'p18a'); ?></div>
                    </strong></td>
                <td><strong><?php _e('Record in Transaction Log', 'p18a'); ?></strong></td>
                <td><strong><?php _e('Sync after order', 'p18a'); ?></strong></td>
                <td><strong><?php _e('Auto sync', 'p18a'); ?></strong></td>
                <td><strong><?php _e('Last sync', 'p18a'); ?></strong></td>
                <td><strong><?php _e('Manual sync', 'p18a'); ?></strong></td>

                <td>
                    <span title="Use this column to overwrite the GET odata header, in order to use a custom filter."><strong><?php _e('Extra data', 'p18a'); ?></strong></span>
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
                    <?php _e('Items Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_items_priority" form="p18aw-sync"
                           value="1" <?php if ($this->option('log_items_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_items_priority" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('auto_sync_items_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('auto_sync_items_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('auto_sync_items_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('auto_sync_items_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_items_priority">
                    <?php
                    if ($timestamp = $this->option('items_priority_update', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync"
                       data-sync="sync_items_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
					<textarea style="width:300px !important; height:45px !important;" name="sync_items_priority_config"
                              form="p18aw-sync"
                              placeholder="{&quot;days_back&quot;:&quot;13&quot;,&quot;additional_url&quot;:&quot;and PARTNAME ne '000'&quot;,&quot;search_by&quot;:&quot;PARTNAME&quot;,&quot;is_update_products&quot;:&quot;true&quot;,&quot;categories&quot;:&quot;SPEC1,SPEC2,FAMILYDES&quot;,&quot;is_load_image&quot;:&quot;false&quot;
                    }"
                    ><?php echo $this->option('sync_items_priority_config') ?></textarea>
                </td>

            </tr>
            <tr>
                <td class="p18a-label">
                    <?php _e('Items Priority Variation > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_items_priority_variation" form="p18aw-sync"
                           value="1" <?php if ($this->option('log_items_priority_variation')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_items_priority_variation" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('auto_sync_items_priority_variation')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('auto_sync_items_priority_variation') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('auto_sync_items_priority_variation') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('auto_sync_items_priority_variation') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_items_priority_variation">
                    <?php
                    if ($timestamp = $this->option('items_priority_variation_update', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync"
                       data-sync="sync_items_priority_variation"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <textarea style="width:300px !important; height:45px !important;"
                              name="sync_variations_priority_config"
                              form="p18aw-sync"><?= $this->option('sync_variations_priority_config') ?></textarea>
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <?php _e('Gallery Attachments Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox"  name="log_attachments_priority" form="p18aw-sync" value="1" <?php if($this->option('log_attachments_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_attachments_priority" form="p18aw-sync">
                        <option value="" <?php if( ! $this->option('auto_sync_attachments_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_attachments_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_attachments_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_attachments_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_attachments_priority">
                    <?php 
                    if ($timestamp = $this->option('attachments_priority_update', false)) {
                         echo(get_date_from_gmt(date($format, $timestamp),$format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync" data-sync="sync_attachments_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
					<textarea style="width:300px !important; height:45px !important;"  name="sync_attachments_priority_config"
                              form="p18aw-sync"
                              placeholder=""
                    ><?php echo  stripslashes($this->option('sync_attachments_priority_config'))?></textarea >
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
                    <?php _e('Items Web > Priority', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_items_web" form="p18aw-sync"
                           value="1" <?php if ($this->option('log_items_web')) echo 'checked'; ?> disabled/>
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_items_web" form="p18aw-sync" disabled>
                        <option value="" <?php if (!$this->option('auto_sync_items_web')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('auto_sync_items_web') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('auto_sync_items_web') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('auto_sync_items_web') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_items_web">
                    <?php
                    if ($timestamp = $this->option('items_web_update', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync" data-sync="sync_items_web" disabled ><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="sync_items_web" form="p18aw-sync"
                           placeholder="enter SKU and days_back specific or default"
                           value="<?php echo($this->option('sync_items_web')) ?>" disabled ></input>
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
                    <?php _e('Inevntory Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_inventory_priority" form="p18aw-sync"
                           value="1" <?php if ($this->option('log_inventory_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_inventory_priority" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('auto_sync_inventory_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('auto_sync_inventory_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('auto_sync_inventory_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('auto_sync_inventory_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_inventory_priority">
                    <?php
                    if ($timestamp = $this->option('inventory_priority_update', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync"
                       data-sync="sync_inventory_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="sync_inventory_warhsname"
                           placeholder="Warehouse code, or empty for availability" form="p18aw-sync"
                           value="<?= $this->option('sync_inventory_warhsname') ?>"
                    >
                    </input>
                </td>
            </tr>


            <tr>
                <td class="p18a-label">
                    <?php _e('Price lists Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_pricelist_priority" form="p18aw-sync"
                           value="1" <?php if ($this->option('log_pricelist_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_pricelist_priority" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('auto_sync_pricelist_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('auto_sync_pricelist_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('auto_sync_pricelist_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('auto_sync_pricelist_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_pricelist_priority">
                    <?php
                    if ($timestamp = $this->option('pricelist_priority_update', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync"
                       data-sync="sync_pricelist_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="sync_pricelist_priority_warhsname" form="p18aw-sync"
                           value="<?php echo($this->option('sync_pricelist_priority_warhsname')) ?>">

                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <?php _e('Packs Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_packs_priority" form="p18aw-sync"
                           value="1" <?php if ($this->option('log_packs_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_packs_priority" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('auto_sync_packs_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('auto_sync_packs_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('auto_sync_packs_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('auto_sync_packs_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_packs_priority">
                    <?php
                    if ($timestamp = $this->option('packs_priority_update', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync"
                       data-sync="sync_packs_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="static_odata_header_sync_packs_priority"
                           form="p18aw-sync"><?= $this->option('static_odata_header_sync_packs_priority') ?></input>
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <?php _e('Product Family Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_productfamily_priority" form="p18aw-sync"
                           value="1" <?php if ($this->option('log_pricelist_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_productfamily_priority" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('auto_sync_productfamily_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('auto_sync_productfamily_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('auto_sync_productfamily_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('auto_sync_productfamily_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_productfamily_priority">
                    <?php
                    if ($timestamp = $this->option('auto_sync_productfamily_priority_update', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync"
                       data-sync="sync_productfamily_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="static_odata_header_sync_productfamily_priority"
                           form="p18aw-sync"><?= $this->option('static_odata_header_sync_productfamily_priority') ?></input>
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <?php _e('Sites Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_sites_priority" form="p18aw-sync"
                           value="1" <?php if ($this->option('log_sites_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_sites_priority" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('auto_sync_sites_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('auto_sync_sites_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('auto_sync_sites_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('auto_sync_sites_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_sites_priority">
                    <?php
                    if ($timestamp = $this->option('sites_priority_update', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync"
                       data-sync="sync_sites_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="static_odata_header_sync_sites_priority"
                           form="p18aw-sync"><?= $this->option('static_odata_header_sync_sites_priority') ?></input>
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
                    <?php _e('Customer\'s Products, Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_c_products_priority" form="p18aw-sync"
                           value="1" <?php if ($this->option('log_c_products_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_c_products_priority" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('auto_sync_c_products_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('auto_sync_c_products_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('auto_sync_c_products_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('auto_sync_c_products_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_c_products_priority">
                    <?php
                    if ($timestamp = $this->option('c_products_priority_update', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync"
                       data-sync="sync_c_products_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="static_odata_header_sync_c_products_priority"
                           form="p18aw-sync"><?= $this->option('static_odata_header_sync_c_products_priority') ?></input>
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <?php _e('Auto Sync Order Status From Priority', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_sync_order_status_priority" form="p18aw-sync"
                           value="1" <?php if ($this->option('log_sync_order_status_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_order_status_priority" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('auto_sync_order_status_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('auto_sync_order_status_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('auto_sync_order_status_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('auto_sync_order_status_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="auto_sync_order_status_priority">
                    <?php
                    if ($timestamp = $this->option('auto_sync_order_status_priority_update', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync"
                       data-sync="auto_sync_order_status_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>
                <td>
                </td>
                <td>
                </td>
            </tr>
            <?php
            $this->generate_settings('Sync Priority customer as WP user', 'customer_to_wp_user', $format, $format2);
            ?>
            <tr>
                <td>_______________________________</td>
            </tr>
        </table>
        <h1>Sync Orders Control</h1><br>
        <table>
            <thead>
            <td><strong>Priority Document</strong></td>
            <td><strong>On checkout?</strong></td>
            <td><strong>Set Schedule</strong></td>
            <td><strong>Last Schedule run</strong></td>
            <td><strong>Priority Order Field</strong></td>
            </thead>
            <!--  sync reciepts -->
            <tr>
                <td class="p18a-label">
                    <?php _e('Receipts', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="post_receipt_checkout" form="p18aw-sync"
                           value="1" <?php if ($this->option('post_receipt_checkout')) echo 'checked'; ?> />
                </td>
                <td>
                    <select name="cron_receipt" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('cron_receipt')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('cron_receipt') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('cron_receipt') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('cron_receipt') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="time_stamp_cron_receipt">
                    <?php
                    if ($timestamp = $this->option('time_stamp_cron_receipt', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <input type="text" style="width:300px" name="receipt_order_field" form="p18aw-sync"
                           placeholder="Enter BOOKNUM or DETAILS"
                           value="<?= empty($this->option('receipt_order_field')) ? 'BOOKNUM' : $this->option('receipt_order_field') ?>"></input>
                </td>

            </tr>
            <!-- sync ainvoices -->
            <tr>
                <td class="p18a-label">
                    <?php _e('Sales Invoices', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="post_ainvoice_checkout" form="p18aw-sync"
                           value="1" <?php if ($this->option('post_ainvoice_checkout')) echo 'checked'; ?> />
                </td>
                <td>
                    <select name="cron_ainvoice" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('cron_ainvoice')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('cron_ainvoice') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('cron_ainvoice') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('cron_ainvoice') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="time_stamp_cron_ainvoice">
                    <?php
                    if ($timestamp = $this->option('time_stamp_cron_ainvoice', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <input type="text" style="width:300px" name="ainvoice_order_field" form="p18aw-sync"
                           placeholder="Enter BOOKNUM or DETAILS"
                           value="<?= empty($this->option('ainvoice_order_field')) ? 'DETAILS' : $this->option('ainvoice_order_field') ?>"></input>
                </td>
            </tr>
            <!-- sync Orders -->
            <tr>
                <td class="p18a-label">
                    <?php _e('Sales Order', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="post_order_checkout" form="p18aw-sync"
                           value="1" <?php if ($this->option('post_order_checkout')) echo 'checked'; ?> />
                </td>
                <td>
                    <select name="cron_orders" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('cron_orders')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('cron_orders') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('cron_orders') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('cron_orders') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="time_stamp_cron_order">
                    <?php
                    if ($timestamp = $this->option('time_stamp_cron_order', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <input type="text" style="width:300px" name="order_order_field" form="p18aw-sync"
                           placeholder="Enter BOOKNUM or DETAILS"
                           value="<?= empty($this->option('order_order_field')) ? 'BOOKNUM' : $this->option('order_order_field') ?>"></input>
                </td>
            </tr>
            <!-- sync otc -->
            <tr>
                <td class="p18a-label">
                    <?php _e('OTC Invoice', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="post_einvoice_checkout" form="p18aw-sync"
                           value="1" <?php if ($this->option('post_einvoice_checkout')) echo 'checked'; ?> />
                </td>
                <td>
                    <select name="cron_otc" form="p18aw-sync">
                        <option value="" <?php if (!$this->option('cron_otc')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if ($this->option('cron_otc') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if ($this->option('cron_otc') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if ($this->option('cron_otc') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="time_stamp_cron_otc">
                    <?php
                    if ($timestamp = $this->option('time_stamp_cron_otc', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <input type="text" style="width:300px" name="otc_order_field" form="p18aw-sync"
                           placeholder="Enter BOOKNUM or DETAILS"
                           value="<?= empty($this->option('otc_order_field')) ? 'DETAILS' : $this->option('otc_order_field') ?>"></input>
                </td>
            </tr>
            <!-- sync Shipment -->
            <tr>
                <td class="p18a-label">
                    <?php _e('Shipment', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="post_document_d_checkout" form="p18aw-sync"
                           value="1" <?php if ($this->option('post_document_d_checkout')) echo 'checked'; ?> />
                </td>
                <td>
                </td>
                <td>
                </td>
                <td>
                    <input type="text" style="width:300px" name="document_d_order_field" form="p18aw-sync"
                           placeholder="Enter BOOKNUM or DETAILS"
                           value="<?= empty($this->option('document_d_order_field')) ? 'BOOKNUM' : $this->option('document_d_order_field') ?>"></input>
                </td>
            </tr>
            <!-- sync POS -->
            <tr>
                <td class="p18a-label">
                    <?php _e('POS', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="post_pos_checkout" form="p18aw-sync"
                           value="1" <?php if ($this->option('post_pos_checkout')) echo 'checked'; ?> />
                </td>
            </tr>
            <!-- sync registered customers -->
            <tr>
                <td class="p18a-label">
                    <?php _e('Registered Customers', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="post_customers" form="p18aw-sync"
                           value="1" <?php if ($this->option('post_customers')) echo 'checked'; ?> />
                </td>
                <td>
                </td>
                <td data-sync-time="sync_customers_web">
                    <?php
                    if ($timestamp = $this->option('customers_web_update', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp), $format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                </td>
                <td></td>
            </tr>
            <!-- sync prospect -->
            <tr>
                <td class="p18a-label">
                    <?php _e('Prospect', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="post_prospect" form="p18aw-sync"
                           value="1" <?php if ($this->option('post_prospect')) echo 'checked'; ?> />
                </td>
                <td>
                </td>
                <td>
                </td>
                <td>
                    <select name="prospect_field" form="p18aw-sync">
                        <?php $prospect_field = $this->option('prospect_field'); ?>
                        <option value="" <?php if (!$this->option('prospect_field')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <!--option value="" selected disabled hidden>Define which field will be posted as customer number</option-->
                        <option value="prospect_email" <?php if ($this->option('prospect_field') == 'prospect_email') echo 'selected'; ?>><?php _e('By order\'s Email', 'p18a'); ?></option>
                        <!--option value="prospect_email">By order's Email</option-->
                        <option value="prospect_cellphone" <?php if ($this->option('prospect_field') == 'prospect_cellphone') echo 'selected'; ?>><?php _e('By order\'s Cellphone', 'p18a'); ?></option>
                        <!--option value="prospect_cellphone">By order's Cellphone</option-->
                    </select>
                </td>
            </tr>
            <!-- submit -->
            <tr>
                <td class="p18a-label" colspan="6">
                    <input type="submit" class="button-primary" value="<?php _e('Save changes', 'p18a'); ?>"
                           name="p18aw-save-sync" form="p18aw-sync"/>
                </td>
            </tr>
        </table>
    </div>

</div>
