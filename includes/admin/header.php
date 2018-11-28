<?php defined('ABSPATH') or die('No direct script access!'); ?>

<h1>
    <?php echo P18AW_PLUGIN_NAME; ?> 
    <span id="p18a_version"><?php echo P18AW_VERSION; ?></span>
</h1>

<br />

<div id="p18a_tabs_menu">
    <ul>
        <li>
            <a href="<?php echo admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL); ?>" class="<?php if(is_null($this->get('tab'))) echo 'active'; ?>">
                <?php _e('Settings', 'p18a'); ?>
            </a>
        </li>
        <li>
            <a href="<?php echo admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL . '&tab=syncs'); ?>" class="<?php if($this->get('tab') == 'syncs') echo 'active'; ?>">
                <?php _e('Syncs', 'p18a'); ?>
            </a>
        </li>
        <li>
            <a href="<?php echo admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL . '&tab=pricelist'); ?>" class="<?php if($this->get('tab') == 'pricelist') echo 'active'; ?>">
                <?php _e('Price lists', 'p18a'); ?>
            </a>
        </li>
        <?php if($this->get('tab') == 'show-products'): 

            $data = $this->getPriceListData(urldecode($_GET['pricelist']));
        ?>
        <li>
            <a href="#" class="active">
                <?php printf(__('Products assigned to price list %s', 'p18a'), $data['price_list_name']); ?>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>