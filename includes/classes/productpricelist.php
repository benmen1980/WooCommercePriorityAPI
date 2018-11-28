<?php
/**
* @package     PriorityAPI
* @author      Ante Laca <ante.laca@gmail.com>
* @copyright   2018 Roi Holdings
*/

namespace PriorityWoocommerceAPI;


class ProductPriceList extends \WP_List_Table
{
    // information about each price list
    protected $priceListData = [];

    public function __construct()
    {
        parent::__construct();
    }
    
    public function prepare_items()
    {

        $columns   = $this->get_columns();
        $hidden    = $this->get_hidden_columns();
        $sortable  = $this->get_sortable_columns();

        // get all products assigned to price list
        $products = $GLOBALS['wpdb']->get_results('
            SELECT product_sku, price_list_currency, price_list_price
            FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
            WHERE price_list_code = "' . urldecode($_GET['pricelist']) . '"
            AND blog_id = ' . get_current_blog_id(), 
            ARRAY_A
        );

        $ids = [];

        // get product id
        foreach($products as $product) {

            if ($id = wc_get_product_id_by_sku($product['product_sku'])) {

                $ids[] = $id;

                $this->priceListData[$id] = [
                    'currency' => $product['price_list_currency'],
                    'price' => $product['price_list_price'],
                    'sku' => $product['product_sku'],
                ];

            }
        }

        // check for products
        if (empty($ids)) {

            $data = [];

        } else {

            $data = get_posts([
                'post_type' => ['product', 'product_variation'], 
                'posts_per_page' => -1,
                'include' => implode(',', $ids)
            ]);

        }


        #var_dump($data); exit;

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
            'name' => __('Product name', 'p18a'),
            'sku' => __('SKU', 'p18a'),
            'price' => __('Price', 'p18a'),
            'currency' => __('Currency', 'p18a')
        ];

        return $columns;
    }

    public function get_hidden_columns()
    {
        return [];
    }

    public function get_sortable_columns()
    {
        return [];
    }

    public function column_default($item, $name)
    {
        switch($name) {
            case 'name':
                return  $item->post_title;
                ##return '<a href="' . admin_url('post.php?post=' . $item->ID . '&action=edit') . '">' . $item->post_title . '</a>';
                break;
            case 'sku':
            case 'price':
            case 'currency':

                return isset($this->priceListData[$item->ID][$name]) ? $this->priceListData[$item->ID][$name] : '';

                    #return $this->priceListData[$item->ID][$name];
                break;

        }
    }


    private function sort_data($a, $b)
    {
    }
    
}
