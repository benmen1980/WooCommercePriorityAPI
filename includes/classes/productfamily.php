<?php
/**
 * @package     PriorityAPI
 * @author      Ante Laca <ante.laca@gmail.com>
 * @copyright   2018 Roi Holdings
 */

namespace PriorityWoocommerceAPI;


class ProductFamily extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct();
    }

    public function prepare_items()
    {

        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $data = $GLOBALS['wpdb']->get_results('
            SELECT  custname,familyname,discounts
            FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_sync_special_price_product_family
            WHERE blog_id = ' . get_current_blog_id() . '
            ',
            ARRAY_A
        );

        $perPage = 50;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page' => $perPage
        ]);

        $data = array_slice($data, (($currentPage - 1) * $perPage), $perPage);

        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->items = $data;

    }

    public function get_columns()
    {
        $columns = [
            'custname' => __('Customer Number', 'p18a'),
            'familyname' => __('Family Name', 'p18a'),
            'discounts' => __('Discounts %', 'p18a')
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
            'productfamily' => ['productfamily', true]
        ];
    }

    public function column_default($item, $name)
    {
        return $item[$name];
    }
}
