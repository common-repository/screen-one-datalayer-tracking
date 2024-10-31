<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Screen_One_Datalayer_Tracking_JS {
    /** @var object Class Instance */
    private static $instance;

    /** @var array Inherited Analytics options */
    private static $options;

    /**
     * Get the class instance
     */
    public static function get_instance( $options = array() ) {
        return null === self::$instance ? ( self::$instance = new self( $options ) ) : self::$instance;
    }

    /**
     * Constructor
     * Takes our options from the parent class so we can later use them in the JS snippets
     */
    public function __construct( $options = array() ) {
        self::$options = $options;
    }
    
    private static function product_get_category_line( $_product ) {
        $out            = array();
        $variation_data = version_compare( WC_VERSION, '3.0', '<' ) ? $_product->variation_data : ( $_product->is_type( 'variation' ) ? wc_get_product_variation_attributes( $_product->get_id() ) : '' );
        $categories     = get_the_terms( $_product->get_id(), 'product_cat' );

        if ( is_array( $variation_data ) && ! empty( $variation_data ) ) {
            $parent_product = wc_get_product( version_compare( WC_VERSION, '3.0', '<' ) ? $_product->parent->id : $_product->get_parent_id() );
            $categories = get_the_terms( $parent_product->get_id(), 'product_cat' );
        }

        if ( $categories ) {
            foreach ( $categories as $category ) {
                $out[] = $category->name;
            }
        }

        return "'" . esc_js( join( "/", $out ) ) . "',";
    }

    function view_list_item() {
        global $posts;

        $args = array(
            'post_type'			 => 'product',
            'post_status'		   => 'publish',
        );

        $products_query = new WP_Query($args);
        $products = $products_query->posts;

        if($posts[0]->post_type == 'product') {
            $products = $posts;
        }

        $list_name = woocommerce_page_title(false);

        $data_items = "'items': [";
        $data_items_UA = "'impressions': [";
        $index = 1;

        foreach ($products as $product) {
            $product_id = $product->ID;
            $product = wc_get_product($product_id);

             if($product->get_type() == 'variable') {
                $variations = $product->get_available_variations();
                foreach ($variations as $variation => $val) {
                    if ($variation === 0) {
                        $item_variant .= "'item_variant': '";
                        $item_variant_UA .= "'variant': '";
                        foreach ($val["attributes"] as $key => $value) {
                            $item_variant .= $value." ";
                            $item_variant_UA .= $value." ";
                        }
                        $item_variant .= "',";
                        $item_variant_UA .= "',";
                    } else {
                        $item_variant .= "'item_variant".$variation."': '";
                        $item_variant_UA .= "'variant".$variation."': '";
                        foreach ($val["attributes"] as $key => $value) {
                            $item_variant .= $value." ";
                            $item_variant_UA .= $value." ";
                        }
                        $item_variant .= "',";
                        $item_variant_UA .= "',";
                    }
                }
            }

            $categories = get_the_terms($product_id, 'product_cat');
            if (is_array($categories) || is_object($categories)) {
                foreach($categories as $cat => $val) {
                    if ($cat === 0) {
                        $item_category .= "'item_category': '".$val->name."',";
                        $item_category_UA .= "'category': '".$val->name."',";
                    } else {
                        $item_category .= "'item_category".$cat."': '".$val->name."',";
                        $item_category_UA .= "'category".$cat."': '".$val->name."',";
                    }
                }
            }

            $data_items .= "{
                'item_id': '".$product_id."',
                'item_name': '".$product->get_title()."',
                'quantity': '".$product->get_min_purchase_quantity()."',
                'price': '".$product->get_price()."',
                'currency': '".get_option('woocommerce_currency')."',
                'item_list_name': '".$list_name."',
                'index': '".$index."', ";
            $data_items .= $item_category;
            $data_items .= $item_variant;

            $data_items_UA .= "{
                'name': '".$product->get_title()."',
                'id' : '".$product_id."',
                'price': '".$product->get_price()."',
                'list': '".$list_name."',
                'position': '".$index."', ";
            $data_items_UA .= $item_category_UA;
            $data_items_UA .= $item_variant_UA;
                

            unset($item_category);
            unset($item_variant);
            unset($item_category_UA);
            unset($item_variant_UA);

            $data_items .= "},";
            $data_items_UA .= "},";
            $index++;
        }

        $data_items .= "]";
        $data_items_UA .= "]";

        wc_enqueue_js("
            localStorage.setItem('Data tracking view item list', JSON.stringify({
                'source': 'screen-one-woocommerce-tracking', 
                'action': 'view item list',
                'data': {
                    'item_list_name': '".$list_name."',
                    ".$data_items."
                }   
            }));

            localStorage.setItem('Data tracking view item list UA', JSON.stringify({
                'source': 'screen-one-woocommerce-tracking', 
                'action': 'view item list UA',
                'data': {
                    'currencyCode': '".get_option('woocommerce_currency')."',
                    ".$data_items_UA."
                }   
            }));

            window.viewItemListEvent = {
                'data': {
                    'item_list_name': '".$list_name."',
                    ".$data_items."
                }   
            };

            window.viewItemListUAEvent = {
                'data': {
                    'currencyCode': '".get_option('woocommerce_currency')."',
                    ".$data_items_UA."
                }   
            };

            window.postMessage(
                JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'view_item_list',
                    'data': {
                        'item_list_name': '".$list_name."',
                        ".$data_items."
                    }
                }),'*'
            );
            
            window.postMessage(
                JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'view_item_list_UA',
                    'data': {
                        'currencyCode': '".get_option('woocommerce_currency')."',
                        ".$data_items_UA."
                    }
                }),'*'
            );
        ");

    }

    function select_item($product) {
        if($_SERVER['HTTP_REFERER']){
            if ( empty( $product ) ) {
                return;
            }

            $url_referer = $_SERVER['HTTP_REFERER'];

            if($product->get_related()) {
                foreach($product->get_related() as $product_id) {
                    if(get_permalink($product_id) == $url_referer){
                        $item_list = "'item_list_id': 'related_products',
                                    'item_list_name': 'Related products',";
                        break;
                    }
                }
            }

            $categories = get_the_terms($product->get_id(), 'product_cat');

            if (is_array($categories) || is_object($categories)) {
                foreach($categories as $cat => $val) {
                    if ($cat === 0) {
                        $item_category .= "'item_category': '".$val->name."',";
                        $item_category_UA .= "'category': '".$val->name."',";

                    } else {
                        $item_category .= "'item_category".$cat."': '".$val->name."',";
                        $item_category_UA .= "'category".$cat."': '".$val->name."',";
                    }
                }
            }

            if($product->get_type() == 'variable') {
                $variations = $product->get_available_variations();
                foreach ($variations as $variation => $val) {
                    if ($variation === 0) {
                        $item_variant .= "'item_variant': '";
                        $item_variant_UA .= "'variant': '";
                        foreach ($val["attributes"] as $key => $value) {
                            $item_variant .= $value." ";
                            $item_variant_UA .= $value." ";
                        }
                        $item_variant .= "',";
                        $item_variant_UA .= "',";
                    } else {
                        $item_variant .= "'item_variant".$variation."': '";
                        $item_variant_UA .= "'variant".$variation."': '";
                        foreach ($val["attributes"] as $key => $value) {
                            $item_variant .= $value." ";
                            $item_variant_UA .= $value." ";
                        }
                        $item_variant .= "',";
                        $item_variant_UA .= "',";
                    }
                }
            }

            wc_enqueue_js("
                localStorage.setItem('Data tracking select item', JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'select_item',
                    'data': {
                        ".$item_list."
                        'items': [{
                            'item_id': '".$product->get_id()."',     
                            'item_name' : '".$product->get_title()."',
                            'price': '".$product->get_price()."',
                            'quantity': '".$product->get_min_purchase_quantity()."',
                            'currency': '".get_option('woocommerce_currency')."',
                            'index': '1',
                            ".$item_category."
                            ".$item_variant."
                            ".$item_list."
                        }] 
                    }   
                }));

                localStorage.setItem('Data tracking select item UA', JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'select_item',
                    'data': {   
                        'click': {
                            'products': [{
                                'id': '".$product->get_id()."',
                                'name' : '".$product->get_title()."',
                                'price': '".$product->get_price()."',
                                'position': '1',
                                ".$item_category_UA."
                                ".$item_variant_UA."
                            }]
                        }
                    }   
                }));

                window.selectItemEvent = {
                    'data': {
                        ".$item_list."
                        'items': [{
                            'item_id': '".$product->get_id()."',     
                            'item_name' : '".$product->get_title()."',
                            'price': '".$product->get_price()."',
                            'quantity': '".$product->get_min_purchase_quantity()."',
                            'currency': '".get_option('woocommerce_currency')."',
                            'index': '1',
                            ".$item_category."
                            ".$item_variant."
                            ".$item_list."
                        }] 
                    }   
                };

                window.selectItemUAEvent = {
                    'data': {   
                        'click': {
                            'products': [{
                                'id': '".$product->get_id()."',
                                'name' : '".$product->get_title()."',
                                'price': '".$product->get_price()."',
                                'position': '1',
                                ".$item_category_UA."
                                ".$item_variant_UA."
                            }]
                        }
                    }   
                };

                window.postMessage(
                    JSON.stringify({
                        'source': 'screen-one-woocommerce-tracking', 
                        'action': 'select_item',
                        'data': {
                            ".$item_list."
                            'items': [{
                                'item_id': '".$product->get_id()."',     
                                'item_name' : '".$product->get_title()."',
                                'price': '".$product->get_price()."',
                                'currency': '".get_option('woocommerce_currency')."',
                                'quantity': '".$product->get_min_purchase_quantity()."',
                                'index': '1',
                                ".$item_category."
                                ".$item_variant."
                                ".$item_list."
                            }] 
                        }
                    }),'*'
                );
                
                window.postMessage(
                    JSON.stringify({
                        'source': 'screen-one-woocommerce-tracking', 
                        'action': 'select_item_UA',
                        'data': {   
                            'click': {
                                'products': [{
                                    'id': '".$product->get_id()."',
                                    'name' : '".$product->get_title()."',
                                    'price': '".$product->get_price()."',
                                    'position': '1',
                                    ".$item_category_UA."
                                    ".$item_variant_UA."
                                }]
                            }
                        }
                    }),'*'
                );
            ");
        }
        
    }

    /**
     * Tracks a product detail view
     */
    function product_detail( $product ) {
        if ( empty( $product ) ) {
            return;
        }

        $categories = get_the_terms($product->get_id(), 'product_cat');
        

        if (is_array($categories) || is_object($categories)) {
            foreach($categories as $cat => $val) {
                if ($cat === 0) {
                    $item_category .= "'item_category': '".$val->name."',";
                    $item_category_UA .= "'category': '".$val->name."',";
                } else {
                    $item_category .= "'item_category".$cat."': '".$val->name."',";
                    $item_category_UA .= "'category".$cat."': '".$val->name."',";
                }
            }
        }

        if($product->get_type() == 'variable') {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation => $val) {
                if ($variation === 0) {
                    $item_variant .= "'item_variant': '";
                    $item_variant_UA .= "'variant': '";
                    foreach ($val["attributes"] as $key => $value) {
                        $item_variant .= $value." ";
                        $item_variant_UA .= $value." ";
                    }
                    $item_variant .= "',";
                    $item_variant_UA .= "',";
                } else {
                    $item_variant .= "'item_variant".$variation."': '";
                    $item_variant_UA .= "'variant".$variation."': '";
                    foreach ($val["attributes"] as $key => $value) {
                        $item_variant .= $value." ";
                        $item_variant_UA .= $value." ";
                    }
                    $item_variant .= "',";
                    $item_variant_UA .= "',";
                }
            }
        }

        wc_enqueue_js("
            localStorage.setItem('Data tracking view item', JSON.stringify({
                'source': 'screen-one-woocommerce-tracking', 
                'action': 'view_item',
                'data': {
                    'currency': '".get_option('woocommerce_currency')."',
                    'value': '".$product->get_price()."',
                    'items': [{
                        'item_id': '".$product->get_id()."',
                        'item_name' : '".$product->get_title()."',
                        'price': '".$product->get_price()."',
                        'quantity': '".$product->get_min_purchase_quantity()."',
                        'currency': '".get_option('woocommerce_currency')."',
                        'index': '1',
                        ".$item_category."
                        ".$item_variant."
                    }]
                }   
            }));

            localStorage.setItem('Data tracking view item UA', JSON.stringify({
                'source': 'screen-one-woocommerce-tracking', 
                'action': 'view_item_UA',
                'data': {   
                    'detail': {
                        'products': [{
                            'id': '".$product->get_id()."',
                            'name' : '".$product->get_title()."',
                            'price': '".$product->get_price()."',
                            ".$item_category_UA."
                            ".$item_variant_UA."
                        }]
                    }
                }   
            }));

            window.viewItemEvent = {
                'data': {
                    'currency': '".get_option('woocommerce_currency')."',
                    'value': '".$product->get_price()."',
                    'items': [{
                        'item_id': '".$product->get_id()."',
                        'item_name' : '".$product->get_title()."',
                        'price': '".$product->get_price()."',
                        'quantity': '".$product->get_min_purchase_quantity()."',
                        'currency': '".get_option('woocommerce_currency')."',
                        'index': '1',
                        ".$item_category."
                        ".$item_variant."
                    }]
                }   
            };

            window.viewItemUAEvent = {
                'data': {   
                    'detail': {
                        'products': [{
                            'id': '".$product->get_id()."',
                            'name' : '".$product->get_title()."',
                            'price': '".$product->get_price()."',
                            ".$item_category_UA."
                            ".$item_variant_UA."
                        }]
                    }
                }   
            };

            window.postMessage(
                JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'view_item',
                    'data': {
                        'currency': '".get_option('woocommerce_currency')."',
                        'value': '".$product->get_price()."',
                        'items': [{
                            'item_id': '".$product->get_id()."',
                            'item_name' : '".$product->get_title()."',
                            'price': '".$product->get_price()."',
                            'quantity': '".$product->get_min_purchase_quantity()."',
                            'currency': '".get_option('woocommerce_currency')."',
                            ".$item_category."
                            ".$item_variant."
                        }]
                    }
                }),'*'
            );

            window.postMessage(
                JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'view_item_UA',
                        'data': {   
                            'detail': {
                                'products': [{
                                    'id': '".$product->get_id()."',
                                    'name' : '".$product->get_title()."',
                                    'price': '".$product->get_price()."',
                                    ".$item_category_UA."
                                    ".$item_variant_UA."
                                }]
                            }
                        }
                }),'*'
            );
        ");

        if($product->get_related()) {
            $data_items = "'items': [";
            $data_items_UA = "'impressions': [";
            $index = 1;
            foreach($product->get_related() as $product_id) {

                $product_related = wc_get_product($product_id);

                // $variations = $product->get_available_variations();

                if($product_related->get_type() == 'variable') {
                    $variations = $product_related->get_available_variations();
                    foreach ($variations as $variation => $val) {
                        if ($variation === 0) {
                            $item_variant .= "'item_variant': '".$val["attributes"]["attribute_color"]." ".$val["attributes"]["attribute_capacity"]."',";
                        } else {
                            $item_variant .= "'item_variant".$variation."': '".$val["attributes"]["attribute_color"]." ".$val["attributes"]["attribute_capacity"]."',";
                        }
                    }
                }

                $categories = get_the_terms($product_id, 'product_cat');
                if (is_array($categories) || is_object($categories)) {
                    foreach($categories as $cat => $val) {
                        if ($cat === 0) {
                            $item_category .= "'item_category': '".$val->name."',";
                            $item_category_UA .= "'category': '".$val->name."',";
                        } else {
                            $item_category .= "'item_category".$cat."': '".$val->name."',";
                            $item_category_UA .= "'category".$cat."': '".$val->name."',";
                        }
                    }
                }

                $data_items .= "{
                'item_id': '".$product_id."',
                'item_name': '".$product_related->get_title()."',
                'quantity': '".$product_related->get_min_purchase_quantity()."',
                'currency': '".get_option('woocommerce_currency')."',
                'price': '".$product_related->get_price()."',
                'item_list_id': 'related_products',
                'item_list_name': 'Related products',
                'index': '".$index."', ";
                $data_items .= $item_category;
                $data_items .= $item_variant;

                $data_items_UA .= "{
                    'name': '".$product_related->get_title()."',
                    'id' : '".$product_id."',
                    'price': '".$product_related->get_price()."',
                    'list': 'Related products',
                    'position': '".$index."', ";
                $data_items_UA .= $item_category_UA;
                $data_items_UA .= $item_variant_UA;
                    

                unset($item_category);
                unset($item_variant);
                unset($item_category_UA);
                unset($item_variant_UA);

                $data_items .= "},";
                $data_items_UA .= "},";
                $index++;
            }

            $data_items .= "]";
            $data_items_UA .= "]";

            wc_enqueue_js("
                localStorage.setItem('Data tracking view item list', JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'view item list',
                    'data': {
                        'item_list_id': 'related_products',
                        'item_list_name': 'Related products',
                        ".$data_items."
                    }   
                }));

                localStorage.setItem('Data tracking view item list UA', JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'view item list UA',
                    'data': {
                        'currencyCode': '".get_option('woocommerce_currency')."',
                        ".$data_items_UA."
                    }   
                }));

                window.viewItemListEvent = {
                    'data': {
                        'item_list_id': 'related_products',
                        'item_list_name': 'Related products',
                        ".$data_items."
                    }   
                };

                window.viewItemListUAEvent = {
                    'data': {
                        'currencyCode': '".get_option('woocommerce_currency')."',
                        ".$data_items_UA."
                    }   
                };

                window.postMessage(
                    JSON.stringify({
                        'source': 'screen-one-woocommerce-tracking', 
                        'action': 'view_item_list',
                        'data': {
                            'item_list_id': 'related_products',
                            'item_list_name': 'Related products',
                            ".$data_items."
                        }
                    }),'*'
                );
                
                window.postMessage(
                    JSON.stringify({
                        'source': 'screen-one-woocommerce-tracking', 
                        'action': 'view_item_list_UA',
                        'data': {
                            'currencyCode': '".get_option('woocommerce_currency')."',
                            ".$data_items_UA."
                        }
                    }),'*'
                );
            ");

        }
    }

    /**
     * Tracks view cart
     */
    function view_cart( $woocommerce ) {
        if(empty($woocommerce)) return;

        $items = $woocommerce->cart->get_cart();

        $subtotal_price = $woocommerce->cart->subtotal;
        $total_price = $woocommerce->cart->total;

        $coupon_cart_level = [];
        $coupon_product_level = [];
        $discount_cart_level = 0;
        $coupons = [];
        $discount = $woocommerce->cart->get_discount_total();

        foreach( $woocommerce->cart->get_coupons() as $coupon ) {
            $coupons[] = $coupon->get_code();
            if( $coupon->get_discount_type() == 'fixed_cart'){
                $coupon_cart_level[] = $coupon->get_code();
                $discount_cart_level += $coupon->get_amount();
            } else {
                $coupon_product_level[] = $coupon;
            }
        }
        
        $index = 1;
        $data_items = "'items': [";
        foreach($items as $item => $values) {

            // Product ID
            $discount_product_item = 0;

            $coupon_product_item = [];

            $product_exclude_coupon = false;
            
            $productId = $values['data']->get_id(); // if product is variation product, productId will be variationId

            $product = wc_get_product( $productId );

            $categories = get_the_terms($values['product_id'], 'product_cat');

            $variantItems = ($values['variation_id']) ? wc_get_product($values['variation_id']) : '';

            $variantAttr = $variantItems->attributes;

            $variantName = ($values['variation_id']) ? $variantItems->name : '';

            $productPrice = ($values['variation_id']) ? $variantItems->price : $product->get_price();

            // $productSalePrice = ($values['variation_id']) ? $variantItems->sale_price : $product->get_sale_price();

            $id_check_coupon = $values['variation_id'] ? $values['variation_id'] : $values['product_id'];

            // Check productId in coupon
            foreach( $coupon_product_level as $coupon ) {
                if(count($coupon->get_product_ids()) > 0) {
                    if( in_array($id_check_coupon, $coupon->get_product_ids())) {
                        $coupon_product_item[] = $coupon->get_code();
                        if($coupon->get_discount_type() == 'percent') {
                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                        } else {
                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                        }
                    }
                } else if(count($coupon->get_excluded_product_ids()) > 0) {
                    if( !in_array($id_check_coupon, $coupon->get_excluded_product_ids())) {
                        $coupon_product_item[] = $coupon->get_code();
                        if($coupon->get_discount_type() == 'percent') {
                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                        } else {
                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                        }
                    } else {
                        $product_exclude_coupon = true;
                    }
                }
            }

            // Check categoryId in coupon
            if (is_array($categories) || is_object($categories)) {
                foreach($categories as $cat => $val) {
                    if ($cat === 0) {
                        $item_category .= "'item_category': '".$val->name."',";
                    } else {
                        $item_category .= "'item_category".$cat."': '".$val->name."',";
                    }
                    foreach( $coupon_product_level as $coupon ) {
                        if(count($coupon->get_product_categories()) > 0) {
                            if(in_array($val->term_id, $coupon->get_product_categories())) {
                                if(!$product_exclude_coupon) {
                                    if(is_array($coupon_product_item) && !in_array($coupon->get_code(), $coupon_product_item)){
                                        $coupon_product_item[] = $coupon->get_code();
                                        if($coupon->get_discount_type() == 'percent') {
                                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                                        } else {
                                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                                        }
                                    }
                                }
                            }
                        } else if(count($coupon->get_excluded_product_categories()) > 0) {
                            if(!in_array($val->term_id, $coupon->get_excluded_product_categories())) {
                                if(!$product_exclude_coupon) {
                                    if(is_array($coupon_product_item) && !in_array($coupon->get_code(), $coupon_product_item)){
                                        $coupon_product_item[] = $coupon->get_code();
                                        if($coupon->get_discount_type() == 'percent') {
                                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                                        } else {
                                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                                        }
                                    }
                                }
                            } else {
                                if (($key = array_search($coupon->get_code(), $coupon_product_item)) !== false) {
                                    unset($coupon_product_item[$key]); 
                                }
                                if($coupon->get_discount_type() == 'percent') {
                                    $discount_product_item -= ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                                } else {
                                    $discount_product_item -= $coupon->get_amount() * $values['quantity'];
                                }
                            }
                        }
                    }
                }
            }

            foreach( $coupon_product_level as $coupon ) {
                if(count($coupon->get_product_ids()) < 1 && count($coupon->get_excluded_product_ids()) < 1 &&  count($coupon->get_product_categories()) < 1 && count($coupon->get_excluded_product_categories()) < 1){
                    $coupon_product_item[] = $coupon->get_code();
                    if($coupon->get_discount_type() == 'percent') {
                        $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                    } else {
                        $discount_product_item += $coupon->get_amount() * $values['quantity'];
                    }
                }
            }

            $discount_product_item_data = $discount_product_item > 0 ? "'discount': '".$discount_product_item."',": '';

            $data_items .= "{
                'item_id': '".$productId."',
                'item_name': '".$product->get_title()."',
                'quantity': '".$values['quantity']."',
                'price': '".$productPrice."',
                'coupon': '".implode(", ", $coupon_product_item)."',
                'currency' : '".get_option('woocommerce_currency')."',
                ".$discount_product_item_data."
                'item_variant': '".$variantName."',
                'index': '".$index."', ";
            $data_items .= $item_category;

            // if (is_array($variantAttr) || is_object($variantAttr)) {
            //     foreach($variantAttr as $attr => $val) {
            //         $data_items .= "'item_".$attr."': '".$val."',";
            //     }
            // }

            unset($item_category);
            $data_items .= "},";
            $index++;
        }

        $data_items .= "]";

        $discount_data = $discount > 0 ? "'discount': '".$discount."'," : '';
        $discount_cart_level_data = $discount_cart_level > 0 ? "'cart_level_discount': '".$discount_cart_level."'," : '';

        wc_enqueue_js("
            localStorage.setItem('Datatracking_viewCart', JSON.stringify({
                'source': 'screen-one-woocommerce-tracking', 
                'action': 'view_cart',
                'data': {   ".$data_items.",
                            'currency' : '".get_option('woocommerce_currency')."',
                            'value': '".$total_price."',
                            'coupon': '".implode(", ", $coupons)."',
                            ".$discount_data."
                            'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                            ". $discount_cart_level_data."
                        }   
            }));

            window.viewCartEvent = {
                'data': {   ".$data_items.",
                            'currency' : '".get_option('woocommerce_currency')."',
                            'coupon': '".implode(", ",  $coupons)."',
                            ".$discount_data."
                            'value': '".$total_price."',
                            'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                            ". $discount_cart_level_data."
                        }   
            };
            
            window.postMessage(
                JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'view_cart',
                    'data': {   ".$data_items.",
                                'currency' : '".get_option('woocommerce_currency')."',
                                'coupon': '".implode(", ",  $coupons)."',
                                ".$discount_data."
                                'value': '".$total_price."',
                                'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                                ". $discount_cart_level_data."
                            }}),'*')");
    }

    /**
     * Tracks view checkout
     */
    function view_checkout( $fields, $items ) {
        global $woocommerce;

        if(empty($fields)) return;

        $subtotal_price = $woocommerce->cart->subtotal;
        $total_price = $woocommerce->cart->total;

        $coupon_cart_level = [];
        $coupon_product_level = [];
        $discount_cart_level = 0;
        $coupons = [];
        $discount = $woocommerce->cart->get_discount_total();

        foreach( $woocommerce->cart->get_coupons() as $coupon ) {
            $coupons[] = $coupon->get_code();
            if( $coupon->get_discount_type() == 'fixed_cart'){
                $coupon_cart_level[] = $coupon->get_code();
                $discount_cart_level += $coupon->get_amount();
            } else {
                $coupon_product_level[] = $coupon;
            }
        }

        $index = 1;

        $data_fields = "'items': [";
        $data_UA_fields = "'products': [";

        foreach($items as $item => $values) {
            // Product ID

            $discount_product_item = 0;

            $coupon_product_item = [];

            $product_exclude_coupon = false;

            $productId = $values['data']->get_id(); // if product is variation product, productId will be variationId

            $product =  wc_get_product( $productId); 

            $categories = get_the_terms($values['product_id'], 'product_cat');

            $variantItems = ($values['variation_id']) ? wc_get_product($values['variation_id']) : '';

            $variantAttr = $variantItems->attributes;

            $variantName = ($values['variation_id']) ? $variantItems->name : '';

            $productPrice = ($values['variation_id']) ? $variantItems->price : $product->get_price();

            $id_check_coupon = $values['variation_id'] ? $values['variation_id'] : $values['product_id'];

            // $productSalePrice = ($values['variation_id']) ? $variantItems->sale_price : $product->get_sale_price();

            // Check productId in coupon
            foreach( $coupon_product_level as $coupon ) {
                if(count($coupon->get_product_ids()) > 0) {
                    if( in_array($id_check_coupon, $coupon->get_product_ids())) {
                        $coupon_product_item[] = $coupon->get_code();
                        if($coupon->get_discount_type() == 'percent') {
                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                        } else {
                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                        }
                    }
                } else if(count($coupon->get_excluded_product_ids()) > 0) {
                    if( !in_array($id_check_coupon, $coupon->get_excluded_product_ids())) {
                        $coupon_product_item[] = $coupon->get_code();
                        if($coupon->get_discount_type() == 'percent') {
                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                        } else {
                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                        }
                    } else {
                        $product_exclude_coupon = true;
                    }
                }
            }

                // Check categoryId in coupon
            if (is_array($categories) || is_object($categories)) {
                foreach($categories as $cat => $val) {
                    if ($cat === 0) {
                        $item_category .= "'item_category': '".$val->name."',";
                        $item_category_UA .= "'category': '".$val->name."',";
                    } else {
                        $item_category .= "'item_category".$cat."': '".$val->name."',";
                        $item_category_UA .= "'category".$cat."': '".$val->name."',";
                    }
                    foreach( $coupon_product_level as $coupon ) {
                        if(count($coupon->get_product_categories()) > 0) {
                            if(in_array($val->term_id, $coupon->get_product_categories())) {
                                if(!$product_exclude_coupon) {
                                    if(is_array($coupon_product_item) && !in_array($coupon->get_code(), $coupon_product_item)){
                                        $coupon_product_item[] = $coupon->get_code();
                                        if($coupon->get_discount_type() == 'percent') {
                                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                                        } else {
                                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                                        }
                                    }
                                }
                            }
                        } else if(count($coupon->get_excluded_product_categories()) > 0) {
                            if(!in_array($val->term_id, $coupon->get_excluded_product_categories())) {
                                if(!$product_exclude_coupon) {
                                    if(is_array($coupon_product_item) && !in_array($coupon->get_code(), $coupon_product_item)){
                                        $coupon_product_item[] = $coupon->get_code();
                                        if($coupon->get_discount_type() == 'percent') {
                                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                                        } else {
                                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                                        }
                                    }
                                }
                            } else {
                                if (($key = array_search($coupon->get_code(), $coupon_product_item)) !== false) {
                                    unset($coupon_product_item[$key]); 
                                }
                                if($coupon->get_discount_type() == 'percent') {
                                    $discount_product_item -= ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                                } else {
                                    $discount_product_item -= $coupon->get_amount() * $values['quantity'];
                                }
                            }
                        }
                    }
                }
            }

            foreach( $coupon_product_level as $coupon ) {
                if(count($coupon->get_product_ids()) < 1 && count($coupon->get_excluded_product_ids()) < 1 &&  count($coupon->get_product_categories()) < 1 && count($coupon->get_excluded_product_categories()) < 1){
                    $coupon_product_item[] = $coupon->get_code();
                    if($coupon->get_discount_type() == 'percent') {
                        $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                    } else {
                        $discount_product_item += $coupon->get_amount() * $values['quantity'];
                    }
                }
            }

            $discount_product_item_data = $discount_product_item > 0 ? "'discount': '".$discount_product_item."',": '';

            $data_fields .= "{
                'item_id': '".$productId."',
                'item_name': '".$product->get_title()."',
                'quantity': '".$values['quantity']."',
                'price': '".$productPrice."',
                'coupon': '".implode(", ", $coupon_product_item)."',
                ".$discount_product_item_data."
                'index': '".$index."', 
                'item_variant': '".$variantName."',
                'currency' : '".get_option('woocommerce_currency')."',";

            $data_fields .= $item_category;

            // if (is_array($variantAttr) || is_object($variantAttr)) {
            //     foreach($variantAttr as $attr => $val) {
            //         $data_fields .= "'item_".$attr."': '".$val."',";
            //     }
            // }

            $data_UA_fields .= "{
                'id': '".$productId."',
                'name': '".$product->get_title()."',
                'quantity': '".$values['quantity']."',
                'price': '".$productPrice."',
                'coupon': '".implode(", ", $coupon_product_item)."',
                ".$discount_product_item_data."
                'variant': '".$variantName."', ";
            $data_UA_fields .= $item_category_UA;

            unset($item_category);
            unset($item_category_UA);

            $data_fields .= "},";
            $data_UA_fields .= "},";
            $index++;
        }

        $data_fields .= "]";
        $data_UA_fields .= "]";

        $discount_data = $discount > 0 ? "'discount': '".$discount."'," : '';
        $discount_cart_level_data = $discount_cart_level > 0 ? "'cart_level_discount': '".$discount_cart_level."'," : '';

        wc_enqueue_js("
            localStorage.setItem('Data Tracking view checkout', JSON.stringify({
                'source': 'screen-one-woocommerce-tracking', 
                'action': 'view_checkout',
                'data': {
                    ".$data_fields.",
                    'currency' : '".get_option('woocommerce_currency')."',
                    'value' : '".$total_price."',
                    'coupon': '".implode(", ",  $coupons)."',
                    ".$discount_data."
                    'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                    ".$discount_cart_level_data."
                }
            }));

            localStorage.setItem('Data Tracking view checkout UA', JSON.stringify({
                'source': 'screen-one-woocommerce-tracking', 
                'action': 'view_checkout_UA',
                'data': {
                    'checkout': {
                        'actionField': {'step': 1, 'option': 'view checkout'},
                        'value' : '".$total_price."',
                        'coupon': '".implode(", ",  $coupons)."',
                        ".$discount_data."
                        'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                        ".$discount_cart_level_data."
                        ".$data_UA_fields."
                    }
                }
            }));

            window.viewCheckoutEvent = {
                'data': {
                    ".$data_fields.",
                    'currency' : '".get_option('woocommerce_currency')."',
                    'value' : '".$total_price."',
                    'coupon': '".implode(", ",  $coupons)."',
                    ".$discount_data."
                    'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                    ".$discount_cart_level_data."
                }
            };

            window.viewCheckoutUAEvent = {
                'data': {
                    'checkout': {
                        'actionField': {'step': 1, 'option': 'view checkout'},
                        'value' : '".$total_price."',
                        'coupon': '".implode(", ",  $coupons)."',
                        ".$discount_data."
                        'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                        ".$discount_cart_level_data."
                        ".$data_UA_fields."
                    }
                }
            };

            window.postMessage(
                JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'view_checkout',
                    'data': {
                        ".$data_fields.",
                        'currency' : '".get_option('woocommerce_currency')."',
                        'value' : '".$total_price."',
                        'coupon': '".implode(", ",  $coupons)."',
                        ".$discount_data."
                        'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                        ".$discount_cart_level_data."
                    }
                }),'*'
            );

            window.postMessage(
                JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'view_checkout_UA',
                    'data': {
                        'checkout': {
                            'actionField': {'step': 1, 'option': 'view checkout'},
                            'value' : '".$total_price."',
                            'coupon': '".implode(", ",  $coupons)."',
                            ".$discount_data."
                            'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                            ".$discount_cart_level_data."
                            ".$data_UA_fields."
                        }
                    }
                }),'*'
            );
        ");
    }

    function after_checkout_shipping_form() {

        global $woocommerce;

        $items = $woocommerce->cart->get_cart();
        
        $subtotal_price = $woocommerce->cart->subtotal;
        $total_price = $woocommerce->cart->total;

        $coupon_cart_level = [];
        $coupon_product_level = [];
        $discount_cart_level = 0;
        $coupons = [];
        $discount = $woocommerce->cart->get_discount_total();

        foreach( $woocommerce->cart->get_coupons() as $coupon ) {
            $coupons[] = $coupon->get_code();
            if( $coupon->get_discount_type() == 'fixed_cart'){
                $coupon_cart_level[] = $coupon->get_code();
                $discount_cart_level += $coupon->get_amount();
            } else {
                $coupon_product_level[] = $coupon;
            }
        }


        $data_fields = "'items': [";
        $data_UA_fields = "'products': [";
        $index = 1;
        foreach($items as $item => $values) {
            // Product ID
           $discount_product_item = 0;

            $coupon_product_item = [];

            $product_exclude_coupon = false;

            $productId = $values['data']->get_id(); // if product is variation product, productId will be variationId

            $product =  wc_get_product( $productId); 

            $categories = get_the_terms($values['product_id'], 'product_cat');

            $variantItems = ($values['variation_id']) ? wc_get_product($values['variation_id']) : '';

            $variantAttr = $variantItems->attributes;

            $variantName = ($values['variation_id']) ? $variantItems->name : '';

            $productPrice = ($values['variation_id']) ? $variantItems->price : $product->get_price();

            $id_check_coupon = $values['variation_id'] ? $values['variation_id'] : $values['product_id'];

            // $productSalePrice = ($values['variation_id']) ? $variantItems->sale_price : $product->get_sale_price();

            // Check productId in coupon
            foreach( $coupon_product_level as $coupon ) {
                if(count($coupon->get_product_ids()) > 0) {
                    if( in_array($id_check_coupon, $coupon->get_product_ids())) {
                        $coupon_product_item[] = $coupon->get_code();
                        if($coupon->get_discount_type() == 'percent') {
                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                        } else {
                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                        }
                    }
                } else if(count($coupon->get_excluded_product_ids()) > 0) {
                    if( !in_array($id_check_coupon, $coupon->get_excluded_product_ids())) {
                        $coupon_product_item[] = $coupon->get_code();
                        if($coupon->get_discount_type() == 'percent') {
                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                        } else {
                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                        }
                    } else {
                        $product_exclude_coupon = true;
                    }
                }
            }

                // Check categoryId in coupon
            if (is_array($categories) || is_object($categories)) {
                foreach($categories as $cat => $val) {
                    if ($cat === 0) {
                        $item_category .= "'item_category': '".$val->name."',";
                        $item_category_UA .= "'category': '".$val->name."',";
                    } else {
                        $item_category .= "'item_category".$cat."': '".$val->name."',";
                        $item_category_UA .= "'category".$cat."': '".$val->name."',";
                    }
                    foreach( $coupon_product_level as $coupon ) {
                        if(count($coupon->get_product_categories()) > 0) {
                            if(in_array($val->term_id, $coupon->get_product_categories())) {
                                if(!$product_exclude_coupon) {
                                    if(is_array($coupon_product_item) && !in_array($coupon->get_code(), $coupon_product_item)){
                                        $coupon_product_item[] = $coupon->get_code();
                                        if($coupon->get_discount_type() == 'percent') {
                                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                                        } else {
                                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                                        }
                                    }
                                }
                            }
                        } else if(count($coupon->get_excluded_product_categories()) > 0) {
                            if(!in_array($val->term_id, $coupon->get_excluded_product_categories())) {
                                if(!$product_exclude_coupon) {
                                    if(is_array($coupon_product_item) && !in_array($coupon->get_code(), $coupon_product_item)){
                                        $coupon_product_item[] = $coupon->get_code();
                                        if($coupon->get_discount_type() == 'percent') {
                                            $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                                        } else {
                                            $discount_product_item += $coupon->get_amount() * $values['quantity'];
                                        }
                                    }
                                }
                            } else {
                                if (($key = array_search($coupon->get_code(), $coupon_product_item)) !== false) {
                                    unset($coupon_product_item[$key]); 
                                }
                                if($coupon->get_discount_type() == 'percent') {
                                    $discount_product_item -= ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                                } else {
                                    $discount_product_item -= $coupon->get_amount() * $values['quantity'];
                                }
                            }
                        }
                    }
                }
            }

            foreach( $coupon_product_level as $coupon ) {
                if(count($coupon->get_product_ids()) < 1 && count($coupon->get_excluded_product_ids()) < 1 &&  count($coupon->get_product_categories()) < 1 && count($coupon->get_excluded_product_categories()) < 1){
                    $coupon_product_item[] = $coupon->get_code();
                    if($coupon->get_discount_type() == 'percent') {
                        $discount_product_item += ($productPrice * $coupon->get_amount() * $values['quantity']) / 100 ;
                    } else {
                        $discount_product_item += $coupon->get_amount() * $values['quantity'];
                    }
                }
            }

            $discount_product_item_data = $discount_product_item > 0 ? "'discount': '".$discount_product_item."',": '';

            $data_fields .= "{
                'item_id': '".$productId."',
                'item_name': '".$product->get_title()."',
                'quantity': '".$values['quantity']."',
                'currency' : '".get_option('woocommerce_currency')."',
                'price': '".$productPrice."',
                'coupon': '".implode(", ", $coupon_product_item)."',
                ".$discount_product_item_data."
                'index': '".$index."',
                'item_variant': '".$variantName."', ";

            $data_fields .= $item_category;

            // if (is_array($variantAttr) || is_object($variantAttr)) {
            //     foreach($variantAttr as $attr => $val) {
            //         $data_fields .= "'item_".$attr."': '".$val."',";
            //     }
            // }

            $data_UA_fields .= "{
                'id': '".$productId."',
                'name': '".$product->get_title()."',
                'quantity': '".$values['quantity']."',
                'currency' : '".get_option('woocommerce_currency')."',
                'price': '".$productPrice."',
                'coupon': '".implode(", ", $coupon_product_item)."',
                ".$discount_product_item_data."
                'index': '".$index."',
                'variant': '".$variantName."', ";

            $data_UA_fields .= $item_category_UA;

            unset($item_category);
            unset($item_category_UA);

            $data_fields .= "},";
            $data_UA_fields .= "},";
            $index++;
        }

        $data_fields .= "]";
        $data_UA_fields .= "]";

        $discount_data = $discount > 0 ? "'discount': '".$discount."'," : '';
        $discount_cart_level_data = $discount_cart_level > 0 ? "'cart_level_discount': '".$discount_cart_level."'," : '';

        wc_enqueue_js("
            function ll_datalayer_push_shipping_and_payment(shippingInfo, paymentType) {
                localStorage.setItem('Data Tracking add shipping info', JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'add_shipping_info',
                    'data': {
                        ".$data_fields.",
                        'currency' : '".get_option('woocommerce_currency')."',
                        'value' : '".$total_price."',
                        'coupon': '".implode(", ",  $coupons)."',
                        ".$discount_data."
                        'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                        ".$discount_cart_level_data."
                    }
                }));

                localStorage.setItem('Data Tracking add shipping info UA', JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'add_shipping_info_UA',
                    'data': {
                        'checkout': {
                            'actionField': {'step': 2, 'option': shippingInfo },
                            'currency' : '".get_option('woocommerce_currency')."',
                            'value' : '".$total_price."',
                            'coupon': '".implode(", ",  $coupons)."',
                            ".$discount_data."
                            'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                            ".$discount_cart_level_data."
                            ".$data_UA_fields."
                        }
                    }
                }));

                localStorage.setItem('Data Tracking add payment info', JSON.stringify({
                        'source': 'screen-one-woocommerce-tracking', 
                        'action': 'add_payment_info',
                        'data': {
                            ".$data_fields.",
                            'currency' : '".get_option('woocommerce_currency')."',
                            'value' : '".$total_price."',
                            'coupon': '".implode(", ",  $coupons)."',
                            ".$discount_data."
                            'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                            'payment_type': paymentType,
                            ".$discount_cart_level_data."
                        }
                    })
                );

                localStorage.setItem('Data Tracking add payment info UA', JSON.stringify({
                        'source': 'screen-one-woocommerce-tracking',  
                        'action': 'add_payment_info_UA',
                        'data': {
                            'checkout': {
                                'actionField': {'step': 3, 'option': paymentType},
                                'currency' : '".get_option('woocommerce_currency')."',
                                'value' : '".$total_price."',
                                'coupon': '".implode(", ",  $coupons)."',
                                ".$discount_data."
                                'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                                ".$discount_cart_level_data."
                                ".$data_UA_fields."
                            }
                        }
                    })
                );

                window.addShippingInfoEvent = {
                    'data': {
                        ".$data_fields.",
                        'currency' : '".get_option('woocommerce_currency')."',
                        'value' : '".$total_price."',
                        'coupon': '".implode(", ",  $coupons)."',
                        ".$discount_data."
                        'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                        ".$discount_cart_level_data."
                    }
                };

                window.addShippingInfoUAEvent = {
                    'data': {
                        'checkout': {
                            'actionField': {'step': 2, 'option': shippingInfo},
                            'currency' : '".get_option('woocommerce_currency')."',
                            'value' : '".$total_price."',
                            'coupon': '".implode(", ",  $coupons)."',
                            ".$discount_data."
                            'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                            ".$discount_cart_level_data."
                            ".$data_UA_fields."
                        }
                    }
                };

                window.addPaymentInfoEvent = {
                    'data': {
                        ".$data_fields.",
                        'currency' : '".get_option('woocommerce_currency')."',
                        'value' : '".$total_price."',
                        'coupon': '".implode(", ",  $coupons)."',
                        ".$discount_data."
                        'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                        'payment_type': paymentType,
                        ".$discount_cart_level_data."
                    }
                };

                window.addPaymentInfoUAEvent = {
                    'data': {
                        'checkout': {
                            'actionField': {'step': 3, 'option': paymentType},
                            'currency' : '".get_option('woocommerce_currency')."',
                            'value' : '".$total_price."',
                            'coupon': '".implode(", ",  $coupons)."',
                            ".$discount_data."
                            'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                            ".$discount_cart_level_data."
                            ".$data_UA_fields."
                        }
                    }
                };

                window.postMessage(
                    JSON.stringify({
                        'source': 'screen-one-woocommerce-tracking', 
                        'action': 'add_shipping_info',
                        'data': {
                            ".$data_fields.",
                            'currency' : '".get_option('woocommerce_currency')."',
                            'value' : '".$total_price."',
                            'coupon': '".implode(", ",  $coupons)."',
                            'discount': '".$discount."',
                            'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                            'cart_level_discount':  ". $discount_cart_level .",
                        }
                    }),'*'
                );

                window.postMessage(
                    JSON.stringify({
                        'source': 'screen-one-woocommerce-tracking', 
                        'action': 'add_shipping_info_UA',
                        'data': {
                            'checkout': {
                                'actionField': {'step': 2, 'option': shippingInfo},
                                'currency' : '".get_option('woocommerce_currency')."',
                                'value' : '".$total_price."',
                                'coupon': '".implode(", ",  $coupons)."',
                                ".$discount_data."
                                'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                                ".$discount_cart_level_data."
                                ".$data_UA_fields."
                            }
                        }
                    }),'*'
                );

                window.postMessage(
                    JSON.stringify({
                        'source': 'screen-one-woocommerce-tracking', 
                        'action': 'add_payment_info',
                        'data': {
                            ".$data_fields.",
                            'currency' : '".get_option('woocommerce_currency')."',
                            'value' : '".$total_price."',
                            'coupon': '".implode(", ",  $coupons)."',
                            ".$discount_data."
                            'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                            'payment_type': paymentType,
                            ".$discount_cart_level_data."
                        }
                    }),'*'
                );

                window.postMessage(
                    JSON.stringify({
                        'source': 'screen-one-woocommerce-tracking', 
                        'action': 'add_payment_info_UA',
                        'data': {
                            'checkout': {
                                'actionField': {'step': 3, 'option': paymentType},
                                'currency' : '".get_option('woocommerce_currency')."',
                                'value' : '".$total_price."',
                                'coupon': '".implode(", ",  $coupons)."',
                                ".$discount_data."
                                'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                                ".$discount_cart_level_data."
                                ".$data_UA_fields."
                            }
                        }
                    }),'*'
                );

            }

            function getFormData(form){
                var unindexed_array = form.serializeArray();
                var indexed_array = {};
                let differentAddress = false
                
                $.map(unindexed_array, function(n, i){
                    if($('#ship-to-different-address-checkbox:checked').length == 0){
                        if(n['name'].indexOf('billing') > -1){
                            indexed_array[n['name']] = n['value'];
                        }
                    } else {
                        if(n['name'].indexOf('shipping') > -1 && n['name'] !== 'shipping_method[0]'){
                            indexed_array[n['name']] = n['value'];
                        }
                    }
                });

                return indexed_array;
            }

            function ll_datalayer_shipping_ajax() {
                var dlAjaxUrl = '".admin_url('admin-ajax.php')."';

                var paymentType = $.trim(jQuery('#payment .wc_payment_method .input-radio:checked+label').text());

                var shippingInfo = getFormData($('form.checkout'));

                ll_datalayer_push_shipping_and_payment(shippingInfo, paymentType);
            }

            const targetNode = document.querySelector('form.checkout.woocommerce-checkout');

            const config = { attributes: true, attributeFilter: ['class']};

            const callback = function(mutationsList, observer) {
                for(const mutation of mutationsList) {
                    if(mutation.target.classList.contains('processing')){
                        window.addEventListener('beforeunload', ll_datalayer_shipping_ajax , true);
                    } else {
                        window.removeEventListener('beforeunload', ll_datalayer_shipping_ajax , true);
                    }
                }
            };

            const observer = new MutationObserver(callback);

            observer.observe(targetNode, config);

        ");
    }
    
    /**
     * Tracks purchase completed
     */
    function purchase_completed($order) {
        global $woocommerce;

        $coupon_cart_level = [];
        $coupon_product_level = [];
        $discount_cart_level = 0;
        $coupons = [];

        foreach( $order->get_coupon_codes() as $coupon_code ) {
            // Get the WC_Coupon object
            $coupon = new WC_Coupon($coupon_code);

            $coupons[] = $coupon->get_code();

            if( $coupon->get_discount_type() == 'fixed_cart'){
                $coupon_cart_level[] = $coupon_code;
                $discount_cart_level += $coupon->get_amount();
            } else {
                $coupon_product_level[] = $coupon;
            }
        }

        $order_data = array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'order_date' => date('Y-m-d H:i:s', strtotime(get_post($order->get_id())->post_date)),
            'status' => $order->get_status(),
            'shipping_total' => $order->get_total_shipping(),
            'shipping_tax_total' => wc_format_decimal($order->get_shipping_tax(), 2),
            'fee_total' => wc_format_decimal($fee_total, 2),
            'fee_tax_total' => wc_format_decimal($fee_tax_total, 2),
            'tax_total' => wc_format_decimal($order->get_total_tax(), 2),
            'cart_discount' => (defined('WC_VERSION') && (WC_VERSION >= 2.3)) ? wc_format_decimal($order->get_total_discount(), 2) : wc_format_decimal($order->get_cart_discount(), 2),
            'order_discount' => (defined('WC_VERSION') && (WC_VERSION >= 2.3)) ? wc_format_decimal($order->get_total_discount(), 2) : wc_format_decimal($order->get_order_discount(), 2),
            'discount_total' => wc_format_decimal($order->get_total_discount(), 2),
            'order_total' => wc_format_decimal($order->get_total(), 2),
            'order_currency' => $order->get_currency(),
            'payment_method' => $order->get_payment_method(),
            'shipping_method' => $order->get_shipping_method(),
            'customer_id' => $order->get_user_id(),
            'customer_user' => $order->get_user_id(),
            'customer_email' => ($a = get_userdata($order->get_user_id() )) ? $a->user_email : '',
            'billing_first_name' => $order->get_billing_first_name(),
            'billing_last_name' => $order->get_billing_last_name(),
            'billing_company' => $order->get_billing_company(),
            'billing_email' => $order->get_billing_email(),
            'billing_phone' => $order->get_billing_phone(),
            'billing_address_1' => $order->get_billing_address_1(),
            'billing_address_2' => $order->get_billing_address_2(),
            'billing_postcode' => $order->get_billing_postcode(),
            'billing_city' => $order->get_billing_city(),
            'billing_state' => $order->get_billing_state(),
            'billing_country' => $order->get_billing_country(),
            'shipping_first_name' => $order->get_shipping_first_name(),
            'shipping_last_name' => $order->get_shipping_last_name(),
            'shipping_company' => $order->get_shipping_company(),
            'shipping_address_1' => $order->get_shipping_address_1(),
            'shipping_address_2' => $order->get_shipping_address_2(),
            'shipping_postcode' => $order->get_shipping_postcode(),
            'shipping_city' => $order->get_shipping_city(),
            'shipping_state' => $order->get_shipping_state(),
            'shipping_country' => $order->get_shipping_country(),
            'customer_note' => $order->get_customer_note(),
        );

        $discount = $order->get_discount_total();

        $discount_data = $discount > 0 ? "'discount': '".$discount."'," : '';
        $discount_cart_level_data = $discount_cart_level > 0 ? "'cart_level_discount': '".$discount_cart_level."'," : '';

        $purchaseCompleted = '';
        $purchaseCompleted .= "{
            'transaction_id': '".$order->get_order_number()."',
            'value': '".$order->total."',
            'tax': '".number_format($order->get_total_tax(), 2 ,".", "")."',
            'shipping': '".number_format($order->calculate_shipping(), 2 , ".", "")."',
            'status':'". $order_data['status'] ."',
            'coupon': '".implode(", ",  $coupons)."',
            ".$discount_data."
            'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
            ".$discount_cart_level_data."
            'billing_address': {
                'billing_first_name':'". $order_data['billing_first_name'] ."',
                'billing_last_name':'". $order_data['billing_last_name'] ."',
                'billing_company':'". $order_data['billing_company'] ."',
                'billing_email':'". $order_data['billing_email'] ."',
                'billing_phone':". $order_data['billing_phone'] .",
                'billing_address_1':'". $order_data['billing_address_1'] ."',
                'billing_address_2':'". $order_data['billing_address_2'] ."',
                'billing_postcode':". $order_data['billing_postcode'] .",
                'billing_city':'". $order_data['billing_city'] ."',
                'billing_state':'". $order_data['billing_state'] ."',
                'billing_country':'". $order_data['billing_country'] ."'
            },
            'shipping_address': {
                'shipping_first_name':'". $order_data['shipping_first_name'] ."',
                'shipping_last_name':'". $order_data['shipping_last_name'] ."',
                'shipping_company':'". $order_data['shipping_company'] ."',
                'shipping_address_1':'". $order_data['shipping_address_1'] ."',
                'shipping_address_2':'". $order_data['shipping_address_2'] ."',
                'shipping_postcode':'". $order_data['shipping_postcode'] ."',
                'shipping_city':'". $order_data['shipping_city'] ."',
                'shipping_state':'". $order_data['shipping_state'] ."',
                'shipping_country':'". $order_data['shipping_country'] ."'
            },
            'customer_note': '".$order_data['customer_note']."',
            'currency': '".$order_data['order_currency']."',";

            $temp = '';
            $i = 0;

            $purchaseCompletedUA = "
                'actionField': {
                    'id': '".$order->get_order_number()."',
                    'revenue': '".$order->total."',
                    'tax': '".number_format($order->get_total_tax(), 2 ,".", "")."',
                    'shipping': '".number_format($order->calculate_shipping(), 2 , ".", "")."',
                    'coupon': '".implode(", ",  $coupons)."',
                    ".$discount_data."
                    'cart_level_coupon': '".implode(", ", $coupon_cart_level)."',
                    ".$discount_cart_level_data."
                },";
            
            $index = 1;
            $purchaseCompleted .= "'items': [";
            $purchaseCompletedUA .= "'products': [";
                foreach ( $order->get_items() as $key => $item ) :

                    $discount_product_item = 0;

                    $coupon_product_item = [];

                    $product_exclude_coupon = false;
                    
                    $product = $order->get_product_from_item($item);
                    $categories = get_the_terms($item['product_id'], 'product_cat');

                    $variantItems = ($item['variation_id']) ? wc_get_product($item['variation_id']) : '';

                    $variantAttr = $variantItems->attributes;

                    $variantName = ($item['variation_id']) ? $variantItems->name : '';

                    $productPrice = ($item['variation_id']) ? $variantItems->price : $product->get_price();

                    $id_check_coupon = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];

                    // $productSalePrice = ($item['variation_id']) ? $variantItems->sale_price : $product->get_sale_price();

                    if (!function_exists('str_contains')) {
                        function str_contains(string $haystack, string $needle): bool
                        {
                            return '' === $needle || false !== strpos($haystack, $needle);
                        }
                    }

                    foreach( $coupon_product_level as $coupon ) {
                        if(count($coupon->get_product_ids()) > 0) {
                            if( in_array($id_check_coupon, $coupon->get_product_ids())) {
                                $coupon_product_item[] = $coupon->get_code();
                                if($coupon->get_discount_type() == 'percent') {
                                    $discount_product_item += ($productPrice * $coupon->get_amount() * $item['quantity']) / 100 ;
                                } else {
                                    $discount_product_item += $coupon->get_amount() * $item['quantity'];
                                }
                            }
                        } else if(count($coupon->get_excluded_product_ids()) > 0) {
                            if( !in_array($id_check_coupon, $coupon->get_excluded_product_ids())) {
                                $coupon_product_item[] = $coupon->get_code();
                                if($coupon->get_discount_type() == 'percent') {
                                    $discount_product_item += ($productPrice * $coupon->get_amount() * $item['quantity']) / 100 ;
                                } else {
                                    $discount_product_item += $coupon->get_amount() * $item['quantity'];
                                }
                            } else {
                                $product_exclude_coupon = true;
                            }
                        }
                    }

                    if (is_array($categories) || is_object($categories)) {
                        foreach($categories as $cat => $val) {
                            if ($cat === 0) {
                                $item_category .= "'item_category': '".$val->name."',";
                                $item_category_UA .= "'category': '".$val->name."',";
                            } else {
                                $item_category .= "'item_category".$cat."': '".$val->name."',";
                                $item_category_UA .= "'category".$cat."': '".$val->name."',";
                            }
                            foreach( $coupon_product_level as $coupon ) {
                                if(count($coupon->get_product_categories()) > 0) {
                                    if(in_array($val->term_id, $coupon->get_product_categories())) {
                                        if(!$product_exclude_coupon) {
                                            if(is_array($coupon_product_item) && !in_array($coupon->get_code(), $coupon_product_item)){
                                                $coupon_product_item[] = $coupon->get_code();
                                                if($coupon->get_discount_type() == 'percent') {
                                                    $discount_product_item += ($productPrice * $coupon->get_amount() * $item['quantity']) / 100 ;
                                                } else {
                                                    $discount_product_item += $coupon->get_amount() * $item['quantity'];
                                                }
                                            }
                                        }
                                    }
                                } else if(count($coupon->get_excluded_product_categories()) > 0) {
                                    if(!in_array($val->term_id, $coupon->get_excluded_product_categories())) {
                                        if(!$product_exclude_coupon) {
                                            if(is_array($coupon_product_item) && !in_array($coupon->get_code(), $coupon_product_item)){
                                                $coupon_product_item[] = $coupon->get_code();
                                                if($coupon->get_discount_type() == 'percent') {
                                                    $discount_product_item += ($productPrice * $coupon->get_amount() * $item['quantity']) / 100 ;
                                                } else {
                                                    $discount_product_item += $coupon->get_amount() * $item['quantity'];
                                                }
                                            }
                                        }
                                    } else {
                                        if (($key = array_search($coupon->get_code(), $coupon_product_item)) !== false) {
                                            unset($coupon_product_item[$key]); 
                                        }
                                        if($coupon->get_discount_type() == 'percent') {
                                            $discount_product_item -= ($productPrice * $coupon->get_amount() * $item['quantity']) / 100 ;
                                        } else {
                                            $discount_product_item -= $coupon->get_amount() * $item['quantity'];
                                        }
                                    }
                                }
                            }
                        }
                    }

                    foreach( $coupon_product_level as $coupon ) {
                        if(count($coupon->get_product_ids()) < 1 && count($coupon->get_excluded_product_ids()) < 1 &&  count($coupon->get_product_categories()) < 1 && count($coupon->get_excluded_product_categories()) < 1){
                            $coupon_product_item[] = $coupon->get_code();
                            if($coupon->get_discount_type() == 'percent') {
                                $discount_product_item += ($productPrice * $coupon->get_amount() * $item['quantity']) / 100 ;
                            } else {
                                $discount_product_item += $coupon->get_amount() * $item['quantity'];
                            }
                        }
                    }

                    $discount_product_item_data = $discount_product_item > 0 ? "'discount': '".$discount_product_item."',": '';

                    $purchaseCompleted .= "{
                        'item_id': '".$item['product_id']."',
                        'item_name': '".$product->get_title()."',
                        'quantity': '".$item['quantity']."',
                        'price': '".$productPrice."',
                        'coupon': '".implode(", ", $coupon_product_item)."',
                        ".$discount_product_item_data." 
                        'index': '".$index."',
                        'item_variant': '".$variantName."', ";

                    $purchaseCompleted .= $item_category;

                    // if (is_array($variantAttr) || is_object($variantAttr)) {
                    //     foreach($variantAttr as $attr => $val) {
                    //         $purchaseCompleted .= "'item_".$attr."': '".$val."',";
                    //     }
                    // }

                    $purchaseCompletedUA .= "{
                        'id': '".$item['product_id']."',
                        'name': '".$product->get_title()."',
                        'quantity': '".$item['quantity']."',
                        'coupon': '".implode(", ", $coupon_product_item)."',
                        ".$discount_product_item_data."
                        'price': '".$productPrice."',
                        'variant': '".$variantName."', ";

                    $purchaseCompletedUA .= $item_category_UA;

                    unset($item_category);
                    unset($item_category_UA);
                    
                    $index++;

                    $purchaseCompleted .= "},";
                    $purchaseCompletedUA .= "},";
                endforeach;
                
            $purchaseCompleted .= "]";
            
        $purchaseCompleted .= "}";

        $purchaseCompletedUA .= "]";

        wc_enqueue_js("

            localStorage.setItem('Data Tracking purchase completed', JSON.stringify({
                'source': 'screen-one-woocommerce-tracking', 
                'action': 'purchase_completed',
                'data': ".$purchaseCompleted."
            }));

            localStorage.setItem('Data Tracking purchase completed UA', JSON.stringify({
                'source': 'screen-one-woocommerce-tracking', 
                'action': 'purchase_completed_UA',
                'data': {
                    'purchase': {
                        ".$purchaseCompletedUA."
                    }
                }
            }));

            window.viewPurchaseCompletedEvent = {
                'data': ".$purchaseCompleted."
            };

             window.viewPurchaseCompletedUAEvent = {
                'data': {
                    'purchase': {
                        ".$purchaseCompletedUA."
                    }
                }
            };

            window.postMessage(
                JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'purchase_completed',
                    'data': {
                        'purchase': {
                            ".$purchaseCompletedUA."
                        }
                    }
                }),'*'
            );

        ");
    }


    /**
     * Tracks remove_product_from_cart
     */
    function remove_from_cart($woocommerce) {

        wc_enqueue_js( "
            var dlAjaxUrl = '".admin_url('admin-ajax.php')."';

            $( document.body ).on( 'click', 'form.woocommerce-cart-form .remove', function(e) {
                
                var aTag = $(e.currentTarget),
                    href = aTag.attr('href');

                var regex = new RegExp('[\\?&]remove_item=([^&#]*)'),
                    results = regex.exec(href);
                if(results !== null) {
                    var cart_key_item = results[1];

                    const dataQuery = {
                        action: 'dlQueryRemoveItem',
                        cart_key_item: cart_key_item,
                    }

                    $.ajax({
                        url: dlAjaxUrl,
                        type: 'GET',
                        data: dataQuery,
                        success: function(response) {
                            const { itemRemoveGA4, itemRemoveUA ,valueRemoveCart } = JSON.parse(response);

                            localStorage.setItem('Data tracking remove from cart', JSON.stringify({
                                'source': 'screen-one-woocommerce-tracking', 
                                'action': 'remove_from_cart',
                                'data': { 
                                    'currency' : '".get_option('woocommerce_currency')."',
                                    'value': valueRemoveCart,
                                    'items': [itemRemoveGA4]  
                                }   
                            }));

                            localStorage.setItem('Data tracking remove from cart UA ', JSON.stringify({
                                'source': 'screen-one-woocommerce-tracking', 
                                'action': 'remove_from_cart_UA',
                                'data': {   
                                    'remove': {
                                        'products': [itemRemoveUA]
                                    }
                                }   
                            }));

                            window.removeFromCartEvent = {
                                'data': { 
                                    'currency' : '".get_option('woocommerce_currency')."',
                                    'value': valueRemoveCart,
                                    'items': [ itemRemoveGA4 ]
                                }  
                            };

                            window.removeFromCartUAEvent = {
                                'data': {'remove': { 'products': [ itemRemoveUA ] }}  
                            };

                            window.postMessage(
                                JSON.stringify({
                                    'source': 'screen-one-woocommerce-tracking', 
                                    'action': 'remove_from_cart',
                                    'data': { 
                                        'currency' : '".get_option('woocommerce_currency')."',
                                        'value': valueRemoveCart,
                                        'items': [ itemRemoveGA4 ]
                                    }
                                }),'*'
                            );

                            window.postMessage(
                                JSON.stringify({
                                    'source': 'screen-one-woocommerce-tracking', 
                                    'action': 'remove_from_cart_UA',
                                    'data': {'remove': { 'products': [ itemRemoveUA ] }}
                                }),'*'
                            );

                        },
                        error: function () { console.log('error'); } 
                    });
                }
            });
        " );
    }

    /**
     * Add to cart
     */
    public function event_tracking_code( $selector ) {
        wc_enqueue_js( "
            var dlAjaxUrl = '".admin_url('admin-ajax.php')."';

            $( '" . $selector . "' ).click( function() {
                const _quantityEle = $(this).parent().find('input.qty');
                const _quantity = _quantityEle.length > 0 ? _quantityEle[0].value : '1';

                const dataQuery = {
                    action: 'dlQueryProduct',
                    productId: $(this).data('product_id'),
                    quantity: _quantity
                }
                $.ajax({
                    url: dlAjaxUrl,
                    type: 'GET',
                    data: dataQuery,
                    success: function(response) {
                        const { GA4, UA } = JSON.parse(response);

                        const dataTracking = JSON.stringify({
                            'source': 'screen-one-woocommerce-tracking', 
                            'action': 'add_single_product_to_cart',
                            'data': {
                                'currency' : '".get_option('woocommerce_currency')."',
                                'value': GA4.price,
                                items: [
                                    GA4
                                ] 
                            }
                        })

                        const dataTrackingUA = JSON.stringify({
                            'source': 'screen-one-woocommerce-tracking', 
                            'action': 'add_single_product_to_cart_UA',
                            'data': {
                                'currencyCode': '".get_option('woocommerce_currency')."',
                                'add': {
                                    'products': [
                                        UA
                                    ]
                                }
                            }
                        })

                        localStorage.setItem('Data Tracking add to cart', JSON.stringify({
                            'data': {
                                'currency' : '".get_option('woocommerce_currency')."',
                                'value': GA4.price,
                                items: [GA4]
                                }
                            })
                        );
                        localStorage.setItem('Data Tracking add to cart UA', JSON.stringify({
                            'data': {
                                'currencyCode': '".get_option('woocommerce_currency')."',
                                'add': {
                                    'products': [
                                        UA
                                    ]
                                }
                            }
                        }));

                        window.postMessage(dataTracking, '*');
                        window.postMessage(dataTrackingUA, '*');
                    },
                    error: function () { console.log('error'); } 
                });
                
            });
        " );        
    }

    function add_to_cart_advanced($cart_item_key, $product_id, $quantity, $variation_id) {
        $product = wc_get_product( $product_id );
        
        $categories = get_the_terms($product_id, 'product_cat');

        $cate = json_encode($categories);

        $get_category_ids = json_encode($product->get_category_ids());

        $get_tag_ids = json_encode($product->get_tag_ids());

        $variantItems = ($variation_id) ? wc_get_product($variation_id) : '{}';

        $variant_name = ($variation_id) ? $variantItems->name : '';

        $productPrice = ($variation_id) ? $variantItems->price : $product->get_price();
        // $productSalePrice = ($variation_id) ? $variantItems->sale_price : $product->get_sale_price();

        if($product->get_type() == 'variable') {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation => $val) {
                if ($variation === 0) {
                    $item_variant .= "'item_variant': '";
                    $item_variant_UA .= "'variant': '";
                    foreach ($val["attributes"] as $key => $value) {
                        $item_variant .= $value." ";
                        $item_variant_UA .= $value." ";
                    }
                    $item_variant .= "',";
                    $item_variant_UA .= "',";
                } else {
                    $item_variant .= "'item_variant".$variation."': '";
                    $item_variant_UA .= "'variant".$variation."': '";
                    foreach ($val["attributes"] as $key => $value) {
                        $item_variant .= $value." ";
                        $item_variant_UA .= $value." ";
                    }
                    $item_variant .= "',";
                    $item_variant_UA .= "',";
                }
            }
        }

        $var_categories = "_categoriesAdd".$product_id;
        $var_itemDetail = "itemDetail".$product_id;
        $var_products = "products".$product_id;
        $var_dataTracking = "dataTracking".$product_id;
        $var_dataTrackingUA = "dataTrackingUA".$product_id;

        wc_enqueue_js("
            const ".$var_categories." = ".$cate.";
            const ".$var_itemDetail." = {   
                'item_name': '".$product->get_name()."',
                'item_id': '".$product_id."',
                'price': '".$productPrice."',
                'quantity': '".$quantity."',
                'index': indexAddToCart,
                'item_list_name': '". $list_name."',
                ".$item_variant."
            };

            const  ".$var_products." = { 
                'name': '".$product->get_name()."',
                'id': '".$product_id."',
                'price': '".$productPrice."',
                'quantity': '".$quantity."',
                'list': '". $list_name."',
                ".$item_variant_UA."
            };

            if (".$var_categories.".length > 0) {
                ".$var_categories.".forEach((cate, index) => {
                    if (index == 0) {
                        ".$var_itemDetail."['item_category'] = cate.name;
                        ".$var_products."['category'] = cate.name
                    } else {
                        ".$var_itemDetail."['item_category'+index] = cate.name;
                        ".$var_products."['category'+index] = cate.name;
                    }
                });
            }

            indexAddToCart++;

            itemAddToCartGA4.push(".$var_itemDetail.")
            itemAddToCartUA.push(".$var_products.")

            valueAddToCartGA4 += ".$var_itemDetail."['price'] * ".$var_itemDetail."['quantity'];

            const ".$var_dataTracking." = {
                'value': valueAddToCartGA4,
                'currency' : '".get_option('woocommerce_currency')."',
                items: itemAddToCartGA4
            }

            const ".$var_dataTrackingUA." = {
                'currencyCode': '".get_option('woocommerce_currency')."',
                'add': {
                    'products': itemAddToCartUA
                }
            }

            localStorage.setItem('Data Tracking add to cart ".$product_id."', JSON.stringify({
                'source': 'screen-one-woocommerce-tracking', 
                'action': 'add_single_product_to_cart',
                'data': ".$var_dataTracking."
            }));
            
            localStorage.setItem('Data Tracking add to cart UA ".$product_id."', JSON.stringify({
                'source': 'screen-one-woocommerce-tracking', 
                'action': 'add_single_product_to_cart',
                'data': ".$var_dataTrackingUA."
            }));

            window.addToCartEvent = {
                'data': ".$var_dataTracking."
            };

            window.addToCartUAEvent = {
                'data': ".$var_dataTrackingUA."
            };

            window.postMessage(
                JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'add_single_product_to_cart',
                    'data': ".$var_dataTracking."
                }
            ),'*')

            window.postMessage(
                JSON.stringify({
                    'source': 'screen-one-woocommerce-tracking', 
                    'action': 'add_single_product_to_cart_UA',
                    'data': ".$var_dataTrackingUA."
                }
            ),'*')
        ");
    }

    /**
    * Quantity product update
    */

    function cart_updated_quantity() {
        global $woocommerce;
        $total_price = $woocommerce->cart->total;
        wc_enqueue_js("
            var dlAjaxUrl = '".admin_url('admin-ajax.php')."';

            $( document.body ).on( 'click', '.woocommerce button[name=\"update_cart\"]', function(e) {
                const dataQuery = {
                    action: 'dlQueryCartData',
                }
                $.ajax({
                    url: dlAjaxUrl,
                    type: 'GET',
                    data: dataQuery,
                    success: function(response) {
                        const {arrayItems} = JSON.parse(response);

                        let valueRemoveCart = 0;
                        let valueAddToCart = 0;

                        const pQuantity = $('.woocommerce-cart-form').find('.input-text.qty');
                        const _arrProducts = [];
                        const itemRemoveGA4 = [], itemRemoveUA = [], itemAddGA4 = [], itemAddUA = [];

                        if (pQuantity.length > 0) {
                            for (let i = 0; i < pQuantity.length; i++) {
                                const _name = pQuantity[i].name;
                                const _quantity = pQuantity[i].valueAsNumber ? pQuantity[i].valueAsNumber : parseInt(pQuantity[i].value);
                                const matches = _name.match(/\[(.*?)\]/);

                                _arrProducts.push({key: matches[1], quantity: _quantity });
                            }
                        }

                        arrayItems.forEach((data, index) => {
                            if (_arrProducts.length > 0) {
                                const existedProductChange = _arrProducts.find(a => a.key == data.key);

                                if (existedProductChange) {
                                    if (data.quantity < existedProductChange.quantity) {
                                        const newQuantity = existedProductChange.quantity - data.quantity;
                                        delete data.itemGA4.coupon
                                        delete data.itemGA4.discount
                                        data.itemGA4.quantity = newQuantity;
                                        data.itemUA.quantity = newQuantity;
                                        itemAddGA4.push(data.itemGA4);
                                        itemAddUA.push(data.itemUA);
                                        valueAddToCart += data.itemGA4.price * data.itemGA4.quantity;
                                    }

                                    if (data.quantity > existedProductChange.quantity) {
                                        const newQuantity = data.quantity - existedProductChange.quantity;
                                        data.itemGA4.discount = (data.itemGA4.discount * newQuantity).toFixed(2);
                                        valueRemoveCart += (newQuantity * data.itemGA4.price) - data.itemGA4.discount;
                                        data.itemGA4.quantity = newQuantity;
                                        data.itemUA.quantity = newQuantity;
                                        itemRemoveGA4.push(data.itemGA4);
                                        itemRemoveUA.push(data.itemUA);
                                    }
                                } else {
                                    totalPriceRemove += data.quantity * newQuantity;
                                    itemRemoveGA4.push(data.itemGA4);
                                    itemRemoveUA.push(data.itemUA);
                                }
                            } else {
                                itemRemoveGA4.push(data.itemGA4);
                                itemRemoveUA.push(data.itemUA);
                            }
                        });

                        if (itemRemoveGA4.length > 0) {
                            localStorage.setItem('Data tracking remove from cart', JSON.stringify({
                                'source': 'screen-one-woocommerce-tracking',
                                'action': 'remove_from_cart',
                                'data': {
                                    'currency' : '".get_option('woocommerce_currency')."',
                                    'value': valueRemoveCart,
                                    'items': itemRemoveGA4
                                }
                            }));

                            window.removeFromCartEvent = {
                                'data': { 
                                    'currency' : '".get_option('woocommerce_currency')."',
                                    'value': valueRemoveCart,
                                    'items': itemRemoveGA4 
                                }
                            };

                            window.postMessage(
                                JSON.stringify({
                                    'source': 'screen-one-woocommerce-tracking',
                                    'action': 'remove_from_cart',
                                    'data': {
                                        'currency' : '".get_option('woocommerce_currency')."',
                                        'value': valueRemoveCart,
                                        'items': itemRemoveGA4 
                                    }
                                }), '*'
                            );
                        }

                        if (itemRemoveUA.length > 0) {
                            localStorage.setItem('Data tracking remove from cart UA ', JSON.stringify({
                                'source': 'screen-one-woocommerce-tracking',
                                'action': 'remove_from_cart_UA',
                                'data': {
                                    'remove': {
                                        'products': itemRemoveUA
                                    }
                                }
                            }));

                            window.removeFromCartUAEvent = {
                                'data': { 'remove': { 'products': itemRemoveUA } }
                            };

                            window.postMessage(
                                JSON.stringify({
                                    'source': 'screen-one-woocommerce-tracking',
                                    'action': 'remove_from_cart_UA',
                                    'data': { 'remove': { 'products': itemRemoveUA } }
                                }), '*'
                            );
                        }

                        if (itemAddGA4.length > 0) {
                            const dataTracking = {
                                'value' : valueAddToCart,
                                'currency' : '".get_option('woocommerce_currency')."',
                                items: itemAddGA4
                            }

                            localStorage.setItem('Data Tracking add to cart', JSON.stringify({
                                'source': 'screen-one-woocommerce-tracking', 
                                'action': 'add_single_product_to_cart',
                                'data': dataTracking
                            }));

                            window.addToCartEvent = { 'data': dataTracking };

                            window.postMessage(
                                JSON.stringify({
                                    'source': 'screen-one-woocommerce-tracking', 
                                    'action': 'add_single_product_to_cart',
                                    'data': dataTracking
                                }
                            ),'*')
                        }

                        if (itemAddUA.length > 0) {
                            const dataTrackingUA = {
                                'currencyCode': '".get_option('woocommerce_currency')."',
                                'add': {
                                    products: itemAddUA
                                }
                            }

                            localStorage.setItem('Data Tracking add to cart UA', JSON.stringify({
                                'source': 'screen-one-woocommerce-tracking', 
                                'action': 'add_single_product_to_cart',
                                'data': dataTrackingUA
                            }));

                            window.addToCartUAEvent = {
                                'data': dataTrackingUA
                            };

                            window.postMessage(
                                JSON.stringify({
                                    'source': 'screen-one-woocommerce-tracking', 
                                    'action': 'add_single_product_to_cart_UA',
                                    'data': dataTrackingUA
                                }
                            ),'*')
                        }
                    },
                    error: function () { console.log('error'); } 
                });
            });
        ");
    }
}