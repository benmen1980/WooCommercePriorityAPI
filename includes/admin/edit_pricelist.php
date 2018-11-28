<?php defined('ABSPATH') or die('No direct script access!'); ?>

<form id="p18aw-price-list" name="p18aw-price-list" method="post" action="<?php echo admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL) . '&tab=edit-price-list&list=' . $data->id;  ?>">
    <?php wp_nonce_field('edit-price-list', 'p18aw-nonce'); ?>
</form>

<div class="wrap">

    <?php include P18AW_ADMIN_DIR . 'header.php'; ?>

    <div class="p18a-page-wrapper">

        <br />
        <h1><?php _e('Edit price list', 'p18a'); ?></h1>
        <table class="p18a">

            <tr>
                <td class="p18a-label">
                    <label for="p18a-list-name"><?php _e('Name', 'p18a'); ?></label>
                </td>
                <td>
                    <input id="p18a-list-name" type="text" name="list_name" value="<?php echo $data->name; ?>" form="p18aw-price-list">                   
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
                    <label for="p18a-list-code"><?php _e('Code', 'p18a'); ?></label>
                </td>
                <td>
                    <input id="p18a-list-code" type="text" name="list_code" value="<?php echo $data->code; ?>"  form="p18aw-price-list">                   
                </td>
            </tr>

            <tr>
                <td class="p18a-label">
                    <label for="p18a-list-currency"><?php _e('Currency', 'p18a'); ?></label>
                </td>
                <td>
                    <input id="p18a-list-currency" type="text" name="list_currency" value="<?php echo $data->currency; ?>"  form="p18aw-price-list">                   
                </td>
            </tr>
                        
        </table>

        <br>

        <input type="submit" class="button-primary" value="<?php _e('Edit price list', 'p18a'); ?>" name="p18aw-price-list-edit" form="p18aw-price-list" />

    </div>
</div>