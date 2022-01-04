<?php
/**
 * @package     PriorityAPI
 * @author      Ante Laca <ante.laca@gmail.com>
 * @copyright   2018 Roi Holdings
 */

namespace PriorityWoocommerceAPI;


class CustomersProducts extends \WP_List_Table
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
            SELECT  custname,partname,custpartname
            FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_customersparts
            ',
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
            'custname' => __('Customer number', 'p18a'),
            'partname' => __('SKU', 'p18a'),
            'custpartname' => __('Customer Part Number', 'p18a')
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
            'sitecode' => ['sitecode', true]
        ];
    }

    public function column_default($item, $name)
    {
        switch($name) {
            case 'price_list_name':

            default:
                return $item[$name];

        }
    }

}
