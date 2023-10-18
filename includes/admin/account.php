<?php defined('ABSPATH') or die('No direct script access!'); ?>

<form id="p18aw-my-account-user" name="p18aw-my-account-user" method="post"
      action="<?php echo admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL . '&tab=myAccountUser'); ?>">
    <?php wp_nonce_field('save-my-account', 'p18aw-nonce'); ?>
</form>

<div class="wrap">

    <?php include P18AW_ADMIN_DIR . 'header.php'; ?>

    <div class="p18a-page-wrapper">

        <br>

        <table class="widefat" cellspacing="0">

            <tbody>

                <tr>
                    <td class="p18a-label">
                        <label for="p18a-obligo"><?php _e('Obligo', 'p18a'); ?></label>
                    </td>
                    <td></td>
                    <td>
                        <input id="p18aw-obligo" type="checkbox" name="obligo"
                            form="p18aw-my-account-user" value ="on" <?php echo ($this->option('obligo') == true) ? 'checked="checked"' : ''; ?>  
                        />
                    </td>
                </tr>
                <tr>
                    <td class="p18a-label">
                        <label for="p18a-accounts"><?php _e('Account Report', 'p18a'); ?></label>
                    </td>
                    <td></td>
                    <td>
                        <?php echo $this->option('account_report'); ?>
                        <input id="p18aw-accounts" type="checkbox" name="account_report"
                            form="p18aw-my-account-user" value ="on" <?php echo ($this->option('account_report') == true) ? 'checked="checked"' : ''; ?> 
                        />
                    </td>
                </tr>
                <tr>
                    <td class="p18a-label">
                        <label for="p18a-quotes"><?php _e('Priority Quotes', 'p18a'); ?></label>
                    </td>
                    <td></td>
                    <td>
                        <input id="p18aw-quotes" type="checkbox" name="priority_quotes"
                            form="p18aw-my-account-user" value ="on" <?php echo ($this->option('priority_quotes') == true) ? 'checked="checked"' : ''; ?> 
                        />
                    </td>
                </tr>
                <tr>
                    <td class="p18a-label">
                        <label for="p18a-orders"><?php _e('Priority Orders', 'p18a'); ?></label>
                    </td>
                    <td></td>
                    <td>
                        <input id="p18aw-orders" type="checkbox" name="priority_orders"
                            form="p18aw-my-account-user" value ="on" <?php echo ($this->option('priority_orders') == true) ? 'checked="checked"' : ''; ?>   
                        />
                    </td>
                </tr>
                <tr>
                    <td class="p18a-label">
                        <label for="p18a-invoices"><?php _e('Priority Invoices', 'p18a'); ?></label>
                    </td>
                    <td></td>
                    <td>
                        <input id="p18aw-invoices" type="checkbox" name="priority_invoices"
                            form="p18aw-my-account-user" value ="on" <?php echo ($this->option('priority_invoices') == true) ? 'checked="checked"' : ''; ?>
                        />
                    </td>
                </tr>
                <tr>
                    <td class="p18a-label">
                        <label for="p18a-receipts"><?php _e('Priority Receipts', 'p18a'); ?></label>
                    </td>
                    <td></td>
                    <td>
                        <input id="p18aw-receipts" type="checkbox" name="priority_receipts"
                            form="p18aw-my-account-user" value ="on" <?php echo ($this->option('priority_receipts') == true) ? 'checked="checked"' : ''; ?>
                        />
                    </td>
                </tr>
                <tr>
                    <td class="p18a-label">
                        <label for="p18a-documents"><?php _e('Priority Documents', 'p18a'); ?></label>
                    </td>
                    <td></td>
                    <td>
                        <input id="p18aw-documents" type="checkbox" name="priority_documents"
                            form="p18aw-my-account-user" value ="on" <?php echo ($this->option('priority_documents') == true) ? 'checked="checked"' : ''; ?> 
                        />
                    </td>
                </tr>
                <tr>
                    <td class="p18a-label">
                        <label for="p18a-delivery"><?php _e('Priority Delivery', 'p18a'); ?></label>
                    </td>
                    <td></td>
                    <td>
                        <input id="p18aw-delivery" type="checkbox" name="priority_delivery"
                            form="p18aw-my-account-user" value ="on" <?php echo ($this->option('priority_delivery') == true) ? 'checked="checked"' : ''; ?> 
                        />
                    </td>
                </tr>
                <tr>
                    <td class="p18a-label">
                        <label for="p18a-return"><?php _e('Priority Return', 'p18a'); ?></label>
                    </td>
                    <td></td>
                    <td>
                        <input id="p18aw-return" type="checkbox" name="priority_return"
                            form="p18aw-my-account-user" value ="on" <?php echo ($this->option('priority_return') == true) ? 'checked="checked"' : ''; ?>
                        />
                    </td>
                </tr>
                <tr>
                    <td class="p18a-label">
                        <label for="p18a-cinvoices"><?php _e('Priority Cinvoices', 'p18a'); ?></label>
                    </td>
                    <td></td>
                    <td>
                        <input id="p18aw-cinvoices" type="checkbox" name="priority_cinvoices"
                            form="p18aw-my-account-user" value ="on" <?php echo ($this->option('priority_cinvoices') == true) ? 'checked="checked"' : ''; ?>
                        />
                    </td>
                </tr>

            </tbody>
        </table>
        <br>

        <input type="submit" class="button-primary" value="<?php _e('Save changes', 'p18a'); ?>"
               name="p18aw-save-my-account" form="p18aw-my-account-user"/>

    </div>
</div>