<?php defined('ABSPATH') or die('No direct script access!'); ?>

<form id="p18aw-sync" name="p18aw-sync" method="post" action="<?php echo admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL . '&tab=syncs'); ?>">
    <?php wp_nonce_field('save-sync', 'p18aw-nonce'); ?>
</form>
<div class="wrap">

    <?php include P18AW_ADMIN_DIR . 'header.php'; ?>

    <div class="p18a-page-wrapper api-sync">

        <br><br>
        <table class="p18a" style="max-width: 300px;"  cellspacing="20">

            <tr>
                <td><strong><div style="width:200px"><?php _e('Sync', 'p18a'); ?></div></strong></td>
                <td><strong><?php _e('Record in Transaction Log', 'p18a'); ?></strong></td>
                <td><strong><?php _e('Sync after order', 'p18a'); ?></strong></td>
                <td><strong><?php _e('Auto sync', 'p18a'); ?></strong></td>
                <td><strong><?php _e('Last sync', 'p18a'); ?></strong></td>
                <td><strong><?php _e('Manual sync', 'p18a'); ?></strong></td>

                <td><span title="Use this column to overwrite the GET odata header, in order to use a custom filter."><strong><?php _e('Static odata header query', 'p18a'); ?></strong></span></td>
            </tr>

            <tr>
                <td class="p18a-label">
                    <?php _e('Items Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox"  name="log_items_priority" form="p18aw-sync" value="1" <?php if($this->option('log_items_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_items_priority" form="p18aw-sync">
                        <option value="" <?php if( ! $this->option('auto_sync_items_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_items_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_items_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_items_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_items_priority">
                    <?php 
                    if ($timestamp = $this->option('items_priority_update', false)) {
                        echo(date('d/m/Y H:i:s', $timestamp));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync" data-sync="sync_items_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="static_odata_header_sync_items_priority" form="p18aw-sync"><?=$this->option('static_odata_header_sync_items_priority')?></input>
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
                    <?php _e('Items Priority Variation > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_items_priority_variation" form="p18aw-sync" value="1" <?php if($this->option('log_items_priority_variation')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_items_priority_variation" form="p18aw-sync">
                        <option value="" <?php if( ! $this->option('auto_sync_items_priority_variation')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_items_priority_variation') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_items_priority_variation') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_items_priority_variation') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_items_priority_variation">
                    <?php
                    if ($timestamp = $this->option('items_priority_variation_update', false)) {
                        echo(date('d/m/Y H:i:s', $timestamp));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync" data-sync="sync_items_priority_variation"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="static_odata_header_sync_items_priority_variation" form="p18aw-sync"><?=$this->option('static_odata_header_sync_items_priority_variation')?></input>
                </td>
            </tr>


            <tr>
                <td class="p18a-label">
                    <?php _e('Items Web > Priority', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_items_web" form="p18aw-sync" value="1" <?php if($this->option('log_items_web')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_items_web" form="p18aw-sync">
                        <option value="" <?php if( ! $this->option('auto_sync_items_web')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_items_web') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_items_web') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_items_web') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_items_web">
                    <?php 
                    if ($timestamp = $this->option('items_web_update', false)) {
                        echo(date('d/m/Y H:i:s', $timestamp));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync" data-sync="sync_items_web"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="static_odata_header_sync_items_web" form="p18aw-sync"><?=$this->option('static_odata_header_sync_items_web')?></input>
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
                    <?php _e('Inevntory Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_inventory_priority" form="p18aw-sync" value="1" <?php if($this->option('log_inventory_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_inventory_priority" form="p18aw-sync">
                        <option value="" <?php if( ! $this->option('auto_sync_inventory_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_inventory_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_inventory_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_inventory_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_inventory_priority">
                    <?php 
                    if ($timestamp = $this->option('inventory_priority_update', false)) {
                        echo(date('d/m/Y H:i:s', $timestamp));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync" data-sync="sync_inventory_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="static_odata_header_sync_inventory_priority" form="p18aw-sync"><?=$this->option('static_odata_header_sync_inventory_priority')?></input>
                </td>
            </tr>


            <tr>
                <td class="p18a-label">
                    <?php _e('Price lists Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_pricelist_priority" form="p18aw-sync" value="1" <?php if($this->option('log_pricelist_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_pricelist_priority" form="p18aw-sync">
                        <option value="" <?php if( ! $this->option('auto_sync_pricelist_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_pricelist_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_pricelist_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_pricelist_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_pricelist_priority">
                    <?php 
                    if ($timestamp = $this->option('pricelist_priority_update', false)) {
                        echo(date('d/m/Y H:i:s', $timestamp));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync" data-sync="sync_pricelist_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="static_odata_header_sync_pricelist_priority" form="p18aw-sync"><?=$this->option('static_odata_header_sync_pricelist_priority')?></input>
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
			        <?php _e('Sites Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_sites_priority" form="p18aw-sync" value="1" <?php if($this->option('log_sites_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_sites_priority" form="p18aw-sync">
                        <option value="" <?php if( ! $this->option('auto_sync_sites_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_sites_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_sites_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_sites_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_sites_priority">
			        <?php
			        if ($timestamp = $this->option('sites_priority_update', false)) {
				        echo(date('d/m/Y H:i:s', $timestamp));
			        } else {
				        _e('Never', 'p18a');
			        }
			        ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync" data-sync="sync_sites_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>

                <td>
                    <input type="text" style="width:300px" name="static_odata_header_sync_sites_priority" form="p18aw-sync"><?=$this->option('static_odata_header_sync_sites_priority')?></input>
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
			        <?php _e('Auto Sync Orders To Priority', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_auto_post_orders_priority" form="p18aw-sync" value="1" <?php if($this->option('log_auto_post_orders_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_orders_priority" form="p18aw-sync">
                        <option value="" <?php if( ! $this->option('auto_sync_orders_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_orders_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_orders_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_orders_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="auto_post_orders_priority">
			        <?php
			        if ($timestamp = $this->option('auto_post_orders_priority_update', false)) {
				        echo(date('d/m/Y H:i:s', $timestamp));
			        } else {
				        _e('Never', 'p18a');
			        }
			        ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync" data-sync="auto_post_orders_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>
               
                <td>
                    <input type="text" style="width:300px" name="static_odata_header_auto_post_orders_priority" form="p18aw-sync"><?=$this->option('static_odata_header_auto_post_orders_priority')?></input>
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
			        <?php _e('Auto Sync Order Status From Priority', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_sync_order_status_priority" form="p18aw-sync" value="1" <?php if($this->option('log_sync_order_status_priority')) echo 'checked'; ?> />
                </td>
                <td></td>
                <td>
                    <select name="auto_sync_order_status_priority" form="p18aw-sync">
                        <option value="" <?php if( ! $this->option('auto_sync_order_status_priority')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_order_status_priority') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_order_status_priority') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_order_status_priority') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="auto_sync_order_status_priority">
			        <?php
			        if ($timestamp = $this->option('auto_sync_order_status_priority_update', false)) {
				        echo(date('d/m/Y H:i:s', $timestamp));
			        } else {
				        _e('Never', 'p18a');
			        }
			        ?>
                </td>
                <td>
                    <a href="#" class="button p18aw-sync" data-sync="auto_sync_order_status_priority"><?php _e('Sync', 'p18a'); ?></a>
                </td>
                <td>

                </td>
                <td>

                </td>
            </tr>




            <tr><td>_______________________________</td></tr>
            <!--  sync reciepts -->
            <tr>
                <td class="p18a-label">
                    <?php _e('Receipts > Priority', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_receipts_priority" form="p18aw-sync" value="1" <?php if($this->option('log_receipts_priority')) echo 'checked'; ?> />
                </td>
                <td>
                    <input type="checkbox" name="sync_onorder_receipts" form="p18aw-sync" value="1" <?php if($this->option('sync_onorder_receipts')) echo 'checked'; ?> />
                </td>
                <td>

                </td>
                <td data-sync-time="sync_receipts_priority">
                    <?php 
                    if ($timestamp = $this->option('receipts_priority_update', false)) {
                        echo(date('d/m/Y H:i:s', $timestamp));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>

                </td>
                <td>
                    <input type="text" style="width:300px" name="email_error_sync_receipts_priority" form="p18aw-sync"><?=$this->option('email_error_sync_receipts_priority')?></textarea>
                </td>
            </tr>


            <tr>

                <!-- sync ainvoices -->
                <td class="p18a-label">
                    <?php _e('Sales Invoices > Priority', 'p18a'); ?><i><b>  beta</b></i>
                </td>
                <td>
                    <input type="checkbox" name="log_ainvoices_priority" form="p18aw-sync" value="1" <?php if($this->option('log_ainvoices_priority')) echo 'checked'; ?> />
                </td>
                <td>
                    <input type="checkbox" disabled name="sync_onorder_ainvoices" form="p18aw-sync" value="1" <?php if($this->option('sync_onorder_ainvoices')) echo 'checked'; ?> />
                </td>
                <td>

                </td>
                <td data-sync-time="sync_ainvoices_priority">
			        <?php
			        if ($timestamp = $this->option('ainvoices_priority_update', false)) {
				        echo(date('d/m/Y H:i:s', $timestamp));
			        } else {
				        _e('Never', 'p18a');
			        }
			        ?>
                </td>
                <td>

                </td>
                <td>
                    <input type="text" style="width:300px" name="email_error_sync_ainvoices_priority" form="p18aw-sync"><?=$this->option('email_error_sync_ainvoices_priority')?></input>
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
                    <?php _e('Customers Web > Priority', 'p18a'); ?>
                </td>
                <td>
                    <input type="checkbox" name="log_customers_web" form="p18aw-sync" value="1" <?php if($this->option('log_customers_web')) echo 'checked'; ?> />
                </td>
                <td colspan="2">

                </td>
                <td data-sync-time="sync_customers_web">
                    <?php
                    if ($timestamp = $this->option('customers_web_update', false)) {
                        echo(date('d/m/Y H:i:s', $timestamp));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <td>

                </td>
                <td>
                    <input type="text" style="width:300px" name="email_error_sync_customers_web" form="p18aw-sync"><?=$this->option('email_error_sync_customers_web')?></input>
                </td>
            </tr>
        
            <tr>
                <td class="p18a-label">
                    <?php _e('Post Order > Priority on check out', 'p18a'); ?>
                </td>
                <td colspan="5">
                    <input type="checkbox" name="post_order_checkout" form="p18aw-sync" value="1" <?php if($this->option('post_order_checkout')) echo 'checked'; ?> />
                </td>
                <td>
                    <input type="text"  style="width:300px" name="email_error_sync_orders_web" form="p18aw-sync"><?=$this->option('email_error_sync_orders_web')?></input>
                </td>
            </tr>


            <tr>
                <td class="p18a-label" colspan="6">
                    <input type="submit" class="button-primary" value="<?php _e('Save changes', 'p18a'); ?>" name="p18aw-save-sync" form="p18aw-sync" />
                </td>
            </tr>
                

        </table>

    </div>
    
</div>
