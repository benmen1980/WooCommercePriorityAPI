<?php defined('ABSPATH') or die('No direct script access!'); ?>

<form id="p18aw-settings" name="p18aw-settings" method="post" action="<?php echo admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL); ?>">
    <?php wp_nonce_field('save-settings', 'p18aw-nonce'); ?>
</form>

<div class="wrap">

    <?php include P18AW_ADMIN_DIR . 'header.php'; ?>

    <div class="p18a-page-wrapper">

        <br>
        <table class="p18a">


            <tr>
                <td class="p18a-label">
                    <label for="p18a-sell_by_pl"><?php _e('Show items from user\'s price list only ?', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-sell_by_pl" type="checkbox" name="sell_by_pl" form="p18aw-settings" <?php if($this->option('sell_by_pl') == true){?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-walkin_hide_price"><?php _e('Hide price for non registered user ?', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18a-walkin_hide_price" type="checkbox" name="walkin_hide_price" form="p18aw-settings" <?php if($this->option('walkin_hide_price') == true){?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-price_method"><?php _e('Sync Price include VAT ?', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-price_method" type="checkbox" name="price_method" form="p18aw-settings" <?php if($this->option('price_method') == true){?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-sites"><?php _e('Use Priority sites ?', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-sites" type="checkbox" name="sites" form="p18aw-settings" <?php if($this->option('sites') == true){?> checked="checked" <?php } ?> />
                </td>
            </tr>
        </table>
        <table class="p18a">
            <tr>
                <td class="p18a-label">
                    <label for="p18a-walkin_number"><?php _e('Guest/Walk in Priority Customer Number', 'p18a'); ?></label>
                </td>
                <td>
                    <input id="p18aw-walkin_number" type="text" name="walkin_number" form="p18aw-settings" size="5" value="<?php echo $this->option('walkin_number'); ?>">
                    Don't forget to check off the 'Change name' field in the Priority Customers form.
                </td>


            </tr>

            <tr>
                <td class="p18a-label">
                    <label for="p18a-variation_field"><?php _e('Variation field in Priority', 'p18a'); ?></label>
                </td>
                <td>
                    <input id="p18aw-variation_field" type="text" name="variation_field" form="p18aw-settings" size="5" value="<?php echo $this->option('variation_field'); ?>">

                     We recommend to user MPARTNAME, ROYY_MODEL or one of the SPECS.


                </td>
                <td class="p18a-label">
                    <label for="p18a-variation_field_title"><?php _e('Title ', 'p18a'); ?></label>
                </td>
                <td>
                    <input id="p18aw-variation_field_title" type="text" name="variation_field_title" form="p18aw-settings" size="5" value="<?php echo $this->option('variation_field_title'); ?>">
                    We recommend to user MPARTDES, ROYY_MODELDES or one of the SPECDES.

                </td>

            </tr>




            <tr>
                <td class="p18a-label">
                    <label for="p18a-item_status"><?php _e('Product Default Status', 'p18a'); ?></label>
                </td>
                <td>
                    <select id="p18aw-item_status" name="item_status" form="p18aw-settings">
                        <option <?php if($this->option('item_status')== 'draft') {?> selected="selected" <?php }   ?>  value="draft">Draft</option>
                        <option <?php if($this->option('item_status')== 'publish') {?> selected="selected" <?php }   ?>  value="publish">Published</option>
                    </select>

                </td>


            </tr>




            <tr>
                <td colspan="2">
                   <label> <?php _e('Shipping methods', 'p18a'); ?> </label>
                </td>
            </tr>

            <?php


            $active_methods = [];

            $zones = WC_Shipping_Zones::get_zones();

            foreach($zones as $zone) {

                $worldwide = new \WC_Shipping_Zone($zone['id']);
                $methods   = $worldwide->get_shipping_methods();
    
                foreach ($methods as $method) {
                    if ($method->enabled === 'yes') {
                        $active_methods[$method->instance_id] = [
                            'id'    => $method->id,
                            'title' => $method->title,
                            'zone'  => $zone['zone_name']
                        ];
                    }
                }

            }

            ?>

            <?php foreach($active_methods as $instance => $data): ?>

            <tr>
                <td class="p18a-label">
                    <label for="p18a-shipping_<?php echo $data['id']; ?>"><?php echo $data['zone']; ?> [<?php echo $data['title']; ?>]</label>
                </td>
                <td>
                    <input id="p18a-shipping_<?php echo $data['id']; ?>" type="text" name="shipping[<?php echo $data['id'] . '_' . $instance; ?>]" value="<?php echo $this->option('shipping_' . $data['id'] . '_' . $instance); ?>" form="p18aw-settings">                   
                </td>
            </tr>
                        
            <?php endforeach; ?>



            <tr>
                <td colspan="2">
                    <label> <?php _e('Payment methods', 'p18a'); ?></label>
                </td>
            </tr>

            <?php


            $gateways = WC()->payment_gateways->payment_gateways;//->get_available_payment_gateways();
            $enabled_gateways = [];

            foreach($gateways as $gateway) {
                if($gateway->enabled == 'yes') {
                    $enabled_gateways[$gateway->id] = $gateway->title;
                }
            }

            ?>

            <?php foreach($enabled_gateways as $id => $title): ?>

            <tr>
                <td class="p18a-label">
                    <label for="p18a-payment_<?php echo $id; ?>"><?php echo $title; ?></label>
                </td>
                <td>
                    <input id="p18a-payment_<?php echo $id; ?>" type="text" name="payment[<?php echo $id; ?>]" value="<?php echo $this->option('payment_' . $id); ?>" form="p18aw-settings">                   
                </td>
            </tr>
                        
            <?php endforeach; ?>

        </table>

        <br>

        <input type="submit" class="button-primary" value="<?php _e('Save changes', 'p18a'); ?>" name="p18aw-save-settings" form="p18aw-settings" />

    </div>
</div>