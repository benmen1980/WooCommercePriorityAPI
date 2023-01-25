<?php 
defined('ABSPATH') or die('No direct script access!');
$format = 'm/d/Y H:i:s';
$format2 = 'd/m/Y H:i:s';
?>

<form id="p18aw-sync-pos" name="p18aw-sync-pos" method="post" action="<?php echo admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL . '&tab=syncPOS'); ?>">
    <?php wp_nonce_field('save-sync-pos', 'p18aw-nonce'); ?>
</form>
<div class="wrap">

    <?php include P18AW_ADMIN_DIR . 'header.php'; ?>

    <div class="p18a-page-wrapper api-sync-pos">

        <br><br>
        <table class="p18a" style="max-width: 300px;"  cellspacing="20">

            <tr>
                <td><strong><div style="width:200px"><?php _e('Sync', 'p18a'); ?></div></strong></td>
                <td><strong><?php _e('Auto sync', 'p18a'); ?></strong></td>
                <td><strong><?php _e('Last sync', 'p18a'); ?></strong></td>
                <!-- <td><strong><?php _e('Manual sync', 'p18a'); ?></strong></td> -->
                <td><span title="Use this column to overwrite the GET odata header, in order to use a custom filter."><strong><?php _e('Extra data', 'p18a'); ?></strong></span></td>
            </tr>
            <!-- dont need because we are synchronizing item from priority - the function is in the thirs plugin -->
            <?php if(false): ?>
                <tr>
                    <td class="p18a-label">
                        <?php _e('Items Priority > Web', 'p18a'); ?>
                    </td>
                    <td>
                        <select name="auto_sync_items_priority_pos" form="p18aw-sync-pos">
                            <option value="" <?php if( ! $this->option('auto_sync_items_priority_pos')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                            <option value="hourly" <?php if($this->option('auto_sync_items_priority_pos') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                            <option value="daily" <?php if($this->option('auto_sync_items_priority_pos') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                            <option value="twicedaily" <?php if($this->option('auto_sync_items_priority_pos') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                        </select>
                    </td>
                    <td data-sync-time="sync_items_priority_pos">
                        <?php
                        if ($timestamp = $this->option('items_priority_pos_update', false)) {
                            echo(get_date_from_gmt(date($format, $timestamp),$format2));
                        } else {
                            _e('Never', 'p18a');
                        }
                        ?>
                    </td>
                    <!-- <td>
                        <a href="#" class="button p18aw-sync" data-sync="sync_items_priority_pos"><?php _e('Sync', 'p18a'); ?></a>
                    </td> -->

                    <td>
                        <textarea style="direction:ltr; width:300px !important; height:45px !important;"  name="sync_items_priority_pos_config"
                                form="p18aw-sync-pos"
                                placeholder="{&quot;days_back&quot;:&quot;13&quot;}"
                        ><?php echo $this->option('sync_items_priority_pos_config')?></textarea >
                    </td>

                </tr>
            <?php endif; ?>
            <tr>
                <td class="p18a-label">
                    <?php _e('Items For Web', 'p18a'); ?>
                </td>
                <td>
                    <select name="auto_sync_items_web_pos" form="p18aw-sync-pos">
                        <option value="" <?php if( ! $this->option('auto_sync_items_web_pos')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_items_web_pos') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_items_web_pos') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_items_web_pos') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_items_web_pos">
                    <?php
                    if ($timestamp = $this->option('items_web_update_pos', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp),$format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <!-- <td>
                    <a href="#" class="button p18aw-sync" data-sync="sync_items_web"><?php _e('Sync', 'p18a'); ?></a>
                </td> -->

                <!-- <td>
                    <input type="text" style="width:300px" name="sync_items_web_pos_config" form="p18aw-sync-pos" placeholder="" value="<?php echo($this->option('sync_items_web_pos'))?>"></input>
                </td> -->
            </tr>

            <tr>
                <td class="p18a-label">
                    <?php _e('Inventory Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <select name="auto_sync_inventory_priority_pos" form="p18aw-sync-pos">
                        <option value="" <?php if( ! $this->option('auto_sync_inventory_priority_pos')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_inventory_priority_pos') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_inventory_priority_pos') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_inventory_priority_pos') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_inventory_priority_pos">
                    <?php
                    if ($timestamp = $this->option('inventory_priority_update_pos', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp),$format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <!-- <td>
                    <a href="#" class="button p18aw-sync" data-sync="sync_inventory_priority_pos"><?php _e('Sync', 'p18a'); ?></a>
                </td> -->

                <td>
                    <textarea style="direction:ltr; width:300px !important; height:45px !important;" name="sync_inventory_pos_config"  form="p18aw-sync-pos" placeholder="{&quot;days_back&quot;:&quot;13&quot;}">
                        <?php echo $this->option('sync_inventory_pos_config')?>
                    </textarea>
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
                    <?php _e('Prices Priority > Web', 'p18a'); ?>
                </td>
                <td>
                    <select name="auto_sync_price_priority_pos" form="p18aw-sync-pos">
                        <option value="" <?php if( ! $this->option('auto_sync_price_priority_pos')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                        <option value="hourly" <?php if($this->option('auto_sync_price_priority_pos') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                        <option value="daily" <?php if($this->option('auto_sync_price_priority_pos') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                        <option value="twicedaily" <?php if($this->option('auto_sync_price_priority_pos') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                    </select>
                </td>
                <td data-sync-time="sync_price_priority_pos">
                    <?php
                    if ($timestamp = $this->option('price_priority_update_pos', false)) {
                        echo(get_date_from_gmt(date($format, $timestamp),$format2));
                    } else {
                        _e('Never', 'p18a');
                    }
                    ?>
                </td>
                <!-- <td>
                    <a href="#" class="button p18aw-sync" data-sync="sync_price_priority_pos"><?php _e('Sync', 'p18a'); ?></a>
                </td> -->

                <td>
                    <textarea style="direction:ltr; width:300px !important; height:45px !important;" name="sync_price_pos_config"  form="p18aw-sync-pos" placeholder="{&quot;days_back&quot;:&quot;13&quot;}">
                        <?php echo $this->option('sync_price_pos_config')?>
                    </textarea>
                </td>
            </tr>
            <?php if(false): ?>
                <tr>
                    <td class="p18a-label">
                        <?php _e('Color Details > Web', 'p18a'); ?>
                    </td>
                    <td>
                        <select name="auto_sync_color_details" form="p18aw-sync-pos">
                            <option value="" <?php if( ! $this->option('auto_sync_color_details')) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                            <option value="hourly" <?php if($this->option('auto_sync_color_details') == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                            <option value="daily" <?php if($this->option('auto_sync_color_details') == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                            <option value="twicedaily" <?php if($this->option('auto_sync_color_details') == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                        </select>
                    </td>
                    <td data-sync-time="sync_color_details">
                        <?php
                        if ($timestamp = $this->option('update_color_details', false)) {
                            echo(get_date_from_gmt(date($format, $timestamp),$format2));
                        } else {
                            _e('Never', 'p18a');
                        }
                        ?>
                    </td>
                </tr>
            <?php endif;?>
            <!-- submit -->
            <tr>
                <td class="p18a-label" colspan="6">
                    <input type="submit" class="button-primary" value="<?php _e('Save changes', 'p18a'); ?>" name="p18aw-save-sync-pos" form="p18aw-sync-pos" />
                </td>
            </tr>
        </table>
    </div>

</div>