<?php
/**
* @package     PriorityAPI
* @author      Ante Laca <ante.laca@gmail.com>
* @copyright   2018 Roi Holdings
*/

namespace PriorityWoocommerceAPI;


class PriceList extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function prepare_items()
    {

        $columns   = $this->get_columns();
        $hidden    = $this->get_hidden_columns();
        $sortable  = $this->get_sortable_columns();

        $data = $GLOBALS['wpdb']->get_results('
            SELECT * 
            FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
            WHERE blog_id = ' . get_current_blog_id() . '
            GROUP BY price_list_code', 
            ARRAY_A
        );

        $perPage = 50;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ]);

        $data = array_slice($data, (($currentPage - 1) * $perPage), $perPage);

        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->items = $data;
    
    }

    public function get_columns()
    {
        $columns = [
            'price_list_name' => __('Name', 'p18a'),
            'price_list_code' => __('Code', 'p18a'),
            //'price_list_price' => __('Price', 'p18a'),
            'price_list_currency' => __('Currency', 'p18a'),
            'show' => __('Show assigned', 'p18a')
        ];

        return $columns;
    }

    public function get_hidden_columns()
    {
        return [];
    }

    public function get_sortable_columns()
    {
        return [
            'timestamp' => ['timestamp', true]
        ];
    }

    public function column_default($item, $name)
    {
        switch($name) {
            case 'price_list_name':
            case 'price_list_code':
           // case 'price_list_price':
            case 'price_list_currency':
                return $item[$name];
                break;
            case 'show':
                return '
                    <a href="' . admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL . '&tab=show-products&pricelist=' .  urlencode($item['price_list_code'])) . '" class="button">' . __('Products', 'p18a') . '</a> &nbsp;
                ';

                //  <a href="' . admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL . '&tab=show-users&pricelist=' .  urlencode($item['price_list_code'])) . '" class="button">' . __('Users', 'p18a') . '</a>
                break;
        }
    }

}
