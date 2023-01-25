<?php defined('ABSPATH') or die('No direct script access!'); ?>

<form id="p18aw-settings" name="p18aw-settings" method="post"
      action="<?php echo admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL); ?>">
    <?php wp_nonce_field('save-settings', 'p18aw-nonce'); ?>
</form>

<div class="wrap">

    <?php include P18AW_ADMIN_DIR . 'header.php'; ?>

    <div class="p18a-page-wrapper">

        <br>


        <!-- ------------------------------->

        <table class="widefat" cellspacing="0">

            <tbody>

            <tr>
                <td class="p18a-label">
                    <label for="p18a-sell_by_pl"><?php _e('Use price lists  ? (B2B mode)', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-sell_by_pl" type="checkbox" name="sell_by_pl"
                           form="p18aw-settings" <?php if ($this->option('sell_by_pl') == true) { ?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-product_family"><?php _e('Use Product Family  ? ', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-product_family" type="checkbox" name="product_family"
                           form="p18aw-settings" <?php if ($this->option('product_family') == true) { ?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-walkin_hide_price"><?php _e('Hide price for non registered user ?', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18a-walkin_hide_price" type="checkbox" name="walkin_hide_price"
                           form="p18aw-settings" <?php if ($this->option('walkin_hide_price') == true) { ?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-price_method"><?php _e('Sync Customer Mcustname ?', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-price_method" size="35" type="checkbox" name="price_method"
                           form="p18aw-settings" <?php if ($this->option('customer_Mcustname') == true) { ?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-sites"><?php _e('Use Priority sites ?', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-sites" type="checkbox" name="sites"
                           form="p18aw-settings" <?php if ($this->option('sites') == true) { ?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-obligo"><?php _e('Show user\'s Obligo in My Account  ?', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-obligo" type="checkbox" name="obligo"
                           form="p18aw-settings" <?php if ($this->option('obligo') == true) { ?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-packs"><?php _e('Use Packs?', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-packs" type="checkbox" name="packs"
                           form="p18aw-settings" <?php if ($this->option('packs') == true) { ?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-sync_personnel"><?php _e('Sync Personnel In Sync Customers', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-sync_personnel" type="checkbox" name="sync_personnel"
                           form="p18aw-settings" <?php if ($this->option('sync_personnel') == true) { ?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-selectusers2"><?php _e('Select user in check out form?', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-selectusers2" type="checkbox" name="selectusers2"
                           form="p18aw-settings" <?php if ($this->option('selectusers2') == true) { ?> checked="checked" <?php } ?> />
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-cardPos"><?php _e('Card POS Sync', 'p18a'); ?></label>
                </td>
                <td></td>
                <td>
                    <input id="p18aw-cardPos" type="checkbox" name="cardPos"
                           form="p18aw-settings" <?php if ($this->option('cardPos') == true) { ?> checked="checked" <?php } ?> />
                </td>
            </tr>
            </tbody>
        </table>
        <table class="widefat fixed" cellspacing="0">
            <col width="135">
            <col width="300">
            <col width="300">
            <col width="300">


            <tr>
                <td class="p18a-label">
                    <label for="p18a-mailing_list_field"><?php _e('On error mailing list', 'p18a'); ?></label>
                </td>
                <td>
                    <input id="p18aw-mailing_list_field" type="text" name="mailing_list_field" form="p18aw-settings"
                           size="5" value="<?php echo $this->option('mailing_list_field'); ?>">
                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-walkin_number"><?php _e('Guest/Walk in Priority Customer Number', 'p18a'); ?></label>
                </td>
                <td>&nbsp;
                    <input id="p18aw-walkin_number" width="48" type="text" name="walkin_number" form="p18aw-settings"
                           value="<?php echo $this->option('walkin_number'); ?>">
                    <h4>Don't forget to check off the 'Change name' field in the Priority Customers form.</h4>
                </td>


            </tr>

            <tr>
                <td class="p18a-label">
                    <label for="p18a-variation_field"><?php _e('Variation field in Priority', 'p18a'); ?></label>
                </td>
                <td>
                    <input id="p18aw-variation_field" type="text" name="variation_field" form="p18aw-settings" size="5"
                           value="<?php echo $this->option('variation_field'); ?>">

                    <h4>We recommend to user MPARTNAME, ROYY_MODEL or one of the SPECS.</h4>


                </td>
            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-variation_field_title"><?php _e('Variation description ', 'p18a'); ?></label>

                </td>
                <td>
                    <input id="p18aw-variation_field_title" type="text" name="variation_field_title"
                           form="p18aw-settings" size="5" value="<?php echo $this->option('variation_field_title'); ?>">
                    <h4>We recommend to user MPARTDES, ROYY_MODELDES or one of the SPECDES.</h4>

                </td>

            </tr>
            <tr>
                <td class="p18a-label">
                    <label for="p18a-item_status"><?php _e('Product Default Status', 'p18a'); ?></label>
                </td>
                <td>
                    <select id="p18aw-item_status" name="item_status" form="p18aw-settings">
                        <option <?php if ($this->option('item_status') == 'draft') { ?> selected="selected" <?php } ?>
                                value="draft">Draft
                        </option>
                        <option <?php if ($this->option('item_status') == 'publish') { ?> selected="selected" <?php } ?>
                                value="publish">Published
                        </option>
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

            foreach ($zones as $zone) {

                $worldwide = new \WC_Shipping_Zone($zone['id']);
                $methods = $worldwide->get_shipping_methods();

                foreach ($methods as $method) {
                    if ($method->enabled === 'yes') {
                        $active_methods[$method->instance_id] = [
                            'id' => $method->id,
                            'title' => $method->title,
                            'zone' => $zone['zone_name']
                        ];
                    }
                }

            }

            ?>

            <?php foreach ($active_methods as $instance => $data): ?>

                <tr>
                    <td class="p18a-label">
                        <label for="p18a-shipping_<?php echo $data['id']; ?>"><?php echo $data['zone']; ?>
                            [<?php echo $data['title']; ?>]</label>
                    </td>
                    <td>
                        <input id="p18a-shipping_<?php echo $data['id']; ?>" type="text"
                               name="shipping[<?php echo $data['id'] . '_' . $instance; ?>]"
                               value="<?php echo $this->option('shipping_' . $data['id'] . '_' . $instance); ?>"
                               form="p18aw-settings">
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

            foreach ($gateways as $gateway) {
                if ($gateway->enabled == 'yes') {
                    $enabled_gateways[$gateway->id] = $gateway->title;
                }
            }

            ?>

            <?php foreach ($enabled_gateways as $id => $title): ?>

                <tr>
                    <td class="p18a-label">
                        <label for="p18a-payment_<?php echo $id; ?>"><?php echo $title; ?></label>
                    </td>
                    <td>
                        <input id="p18a-payment_<?php echo $id; ?>" type="text" name="payment[<?php echo $id; ?>]"
                               value="<?php echo $this->option('payment_' . $id); ?>" form="p18aw-settings">
                    </td>
                </tr>

            <?php endforeach; ?>

        </table>
        <table class="widefat fixed" cellspacing="0">
            <tbody>
            <tr>


                <td>
                    <label for="p18a-update_image"><?php _e('Over write existing images ?', 'p18a'); ?></label>
                    <input id="p18aw-sites" type="checkbox" name="update_image"
                           form="p18aw-settings" <?php if ($this->option('update_image') == true) { ?> checked="checked" <?php } ?> />
                </td>
                <td></td>
            </tr>
            <tr>
            <tr>
                <td>
                    <label for="p18a-setting-config"><?php _e('General Settings', 'p18a'); ?></label>
                </td>
            </tr>
            <tr>
                <td>
                    <textarea id="setting-config" name="setting-config"
                              form="p18aw-settings"> <?php echo stripslashes($this->option('setting-config')) ?> </textarea>
                </td>
            </tr>


        </table>
        </tbody>
        <br>

        <input type="submit" class="button-primary" value="<?php _e('Save changes', 'p18a'); ?>"
               name="p18aw-save-settings" form="p18aw-settings"/>

    </div>
</div>
