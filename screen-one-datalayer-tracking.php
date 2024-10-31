<?php
/**
 * Plugin Name: Screen One Datalayer Tracking
 * Plugin URI: http://wordpress.org/plugins/screen-one-tracking/
 * Description: Tracking data
 * Author: Screen One
 * Version: 2.1.11
 */

 include_once 'includes/class-screen-one-datalayer-tracking.php';
 $screen_one_datalayer_tracking = new Screen_One_Datalayer_Tracking();
 $screen_one_datalayer_tracking->call_hook();

add_action('wp_ajax_dlQueryProduct', 'dlQueryProduct'); //fire get_more_posts on AJAX call for logged-in users;
add_action('wp_ajax_nopriv_dlQueryProduct', 'dlQueryProduct'); //fire get_more_posts on AJAX call for all other users;

function dlQueryProduct() {
    if ($_GET['productId']) {
        $productId = $_GET['productId'];
        $_product =  wc_get_product( $productId); 

        $categories = get_the_terms($productId, 'product_cat');

        $data_fields = array(
            'item_id' => $productId,
            'item_name' => $_product->get_title(),
            'quantity' => $_GET['quantity'],
            'price' => $_product->get_price(),
            'index' => 1,
            // 'sale_price' => $_product->get_sale_price(),
        );

        $data_UA_fields = array(
            'id' => $productId,
            'name' => $_product->get_title(),
            'quantity' => $_GET['quantity'],
            'price' => $_product->get_price(),
            'index' => 1,
        );
        
        foreach($categories as $cat => $val) {
            if ($cat === 0) {
                $data_fields['item_category'] = $val->name;
                $data_UA_fields['category'] = $val->name;
            } else {
                $data_fields['item_category'.$cat] = $val->name;
                $data_UA_fields['category'.$cat] = $val->name;
            }
        }

        echo json_encode(array( 'GA4' => $data_fields , 'UA' =>  $data_UA_fields ));
        die();
    }
}

add_action('wp_ajax_dlQueryRemoveItem', 'dlQueryRemoveItem');
add_action('wp_ajax_nopriv_dlQueryRemoveItem', 'dlQueryRemoveItem');

function dlQueryRemoveItem() {
    if ($_GET['cart_key_item']) {

        global $woocommerce;

        $items_cart = $woocommerce->cart->get_cart();
        $total_price = $woocommerce->cart->total;

        $index = 1;

        foreach ($items_cart as $key => $value) {
            if($value['key'] == $_GET['cart_key_item']) {
                $item_remove = $value;
                break;
            }
            $index++;
        }
            
        $productId = $item_remove['data']->get_id(); // if product is variation product, productId will be variationId

        $product = wc_get_product( $productId );

        $categories = get_the_terms($item_remove['product_id'], 'product_cat');

        $variantItems = ($item_remove['variation_id']) ? wc_get_product($item_remove['variation_id']) : '';

        $variantName = ($item_remove['variation_id']) ? $variantItems->name : '';

        $productPrice = ($item_remove['variation_id']) ? $variantItems->price : $product->get_price();

        $item_remove_GA4 = array(
            'item_id' => $productId,
            'item_name' => $product->get_title(),
            'quantity' => $item_remove['quantity'],
            'price' => $productPrice,
            'index' => $index,
            'currency' => get_option('woocommerce_currency'),
            'item_variant' => $variantName,
        );

        $item_remove_UA = array(
            'id' => $productId,
            'name' => $product->get_title(),
            'quantity' => $item_remove['quantity'],
            'price' => $productPrice,
            'variant' => $variantName,
        );

        $coupon_product_level = [];
        $discount_product_item = 0;
        $product_exclude_coupon = false;
        $coupon_product_item = [];

        foreach( $woocommerce->cart->get_coupons() as $coupon ) {
            if( $coupon->get_discount_type() !== 'fixed_cart'){
                $coupon_product_level[] = $coupon;
            }
        }

        $id_check_coupon = $item_remove['variation_id'] ? $item_remove['variation_id'] : $item_remove['product_id'];

        foreach( $coupon_product_level as $coupon ) {
            if(count($coupon->get_product_ids()) > 0) {
                if( in_array($id_check_coupon, $coupon->get_product_ids())) {
                    $coupon_product_item[] = $coupon->get_code();
                    if($coupon->get_discount_type() == 'percent') {
                        $discount_product_item += ($productPrice * $coupon->get_amount() * $item_remove['quantity']) / 100 ;
                    } else {
                        $discount_product_item += $coupon->get_amount() * $item_remove['quantity'];
                    }
                }
            } else if(count($coupon->get_excluded_product_ids()) > 0) {
                if( !in_array($id_check_coupon, $coupon->get_excluded_product_ids())) {
                    $coupon_product_item[] = $coupon->get_code();
                    if($coupon->get_discount_type() == 'percent') {
                        $discount_product_item += ($productPrice * $coupon->get_amount() * $item_remove['quantity']) / 100 ;
                    } else {
                        $discount_product_item += $coupon->get_amount() * $item_remove['quantity'];
                    }
                } else {
                    $product_exclude_coupon = true;
                }
            }
        }

        if (is_array($categories) || is_object($categories)) {
            foreach($categories as $cat => $val) {
                if ($cat === 0) {
                    $item_remove_GA4['item_category'] = $val->name;
                    $item_remove_UA['category'] = $val->name;
                } else {
                    $item_remove_GA4['item_category'.$cat.''] = $val->name;
                    $item_remove_UA['category'.$cat.''] = $val->name;
                }
                foreach( $coupon_product_level as $coupon ) {
                    if(count($coupon->get_product_categories()) > 0) {
                        if(in_array($val->term_id, $coupon->get_product_categories())) {
                            if(!$product_exclude_coupon) {
                                if(is_array($coupon_product_item) && !in_array($coupon->get_code(), $coupon_product_item)){
                                    $coupon_product_item[] = $coupon->get_code();
                                    if($coupon->get_discount_type() == 'percent') {
                                        $discount_product_item += ($productPrice * $coupon->get_amount() * $item_remove['quantity']) / 100 ;
                                    } else {
                                        $discount_product_item += $coupon->get_amount() * $item_remove['quantity'];
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
                                        $discount_product_item += ($productPrice * $coupon->get_amount() * $item_remove['quantity']) / 100 ;
                                    } else {
                                        $discount_product_item += $coupon->get_amount() * $item_remove['quantity'];
                                    }
                                }
                            }
                        } else {
                            if (($key = array_search($coupon->get_code(), $coupon_product_item)) !== false) {
                                unset($coupon_product_item[$key]); 
                            }
                            if($coupon->get_discount_type() == 'percent') {
                                $discount_product_item -= ($productPrice * $coupon->get_amount() * $item_remove['quantity']) / 100 ;
                            } else {
                                $discount_product_item -= $coupon->get_amount() * $item_remove['quantity'];
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
                    $discount_product_item += ($productPrice * $coupon->get_amount() * $item_remove['quantity']) / 100 ;
                } else {
                    $discount_product_item += $coupon->get_amount() * $item_remove['quantity'];
                }
            }
        }
        
        $item_remove_GA4['coupon'] = implode(", ", $coupon_product_item);

        $value_remove_cart = $productPrice;

        if($discount_product_item > 0){
            $item_remove_GA4['discount'] = $discount_product_item;
            $value_remove_cart = ($productPrice * $item_remove['quantity']) - $discount_product_item;
        }

        echo json_encode(array( 'itemRemoveGA4' => $item_remove_GA4, 'itemRemoveUA' => $item_remove_UA, 'valueRemoveCart' => $value_remove_cart ));

        die();
    }
}

add_action('wp_ajax_dlQueryCartData', 'dlQueryCartData');
add_action('wp_ajax_nopriv_dlQueryCartData', 'dlQueryCartData');

function dlQueryCartData() {
    global $woocommerce;

    $arrayItems = array();
    $items = $woocommerce->cart->get_cart();
    $total_price = $woocommerce->cart->total;

    $coupon_product_level = [];

    foreach( $woocommerce->cart->get_coupons() as $coupon ) {
        if( $coupon->get_discount_type() !== 'fixed_cart'){
            $coupon_product_level[] = $coupon;
        }
    }

    $index = 1;
    foreach($items as $item => $values) {
        // Product ID

        $discount_product_item = 0;
        $product_exclude_coupon = false;
        $coupon_product_item = [];

        $productId = $values['data']->get_id(); // if product is variation product, productId will be variationId

        $product = wc_get_product( $productId );

        $categories = get_the_terms($values['product_id'], 'product_cat');

        $variantItems = ($values['variation_id']) ? wc_get_product($values['variation_id']) : '';

        $variantName = ($values['variation_id']) ? $variantItems->name : '';

        $productPrice = ($values['variation_id']) ? $variantItems->price : $product->get_price();

        $item_GA4 = array(
            'item_id' => $productId,
            'item_name' => $product->get_title(),
            'quantity' => $values['quantity'],
            'price' => $productPrice,
            'item_variant' => $variantName,
            'currency' => get_option('woocommerce_currency'),
            'index' => $index,
        );

        $item_UA = array(
            'id' => $productId,
            'name' => $product->get_title(),
            'quantity' => $values['quantity'],
            'price' => $productPrice,
            'variant' => $variantName,
        );

        $id_check_coupon = $values['variation_id'] ? $values['variation_id'] : $values['product_id'];

        // Check productId in coupon
        foreach( $coupon_product_level as $coupon ) {
            if(count($coupon->get_product_ids()) > 0) {
                if( in_array($id_check_coupon, $coupon->get_product_ids())) {
                    $coupon_product_item[] = $coupon->get_code();
                    if($coupon->get_discount_type() == 'percent') {
                        $discount_product_item += ($productPrice * $coupon->get_amount()) / 100 ;
                    } else {
                        $discount_product_item += $coupon->get_amount();
                    }
                }
            } else if(count($coupon->get_excluded_product_ids()) > 0) {
                if( !in_array($id_check_coupon, $coupon->get_excluded_product_ids())) {
                    $coupon_product_item[] = $coupon->get_code();
                    if($coupon->get_discount_type() == 'percent') {
                        $discount_product_item += ($productPrice * $coupon->get_amount()) / 100 ;
                    } else {
                        $discount_product_item += $coupon->get_amount();
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
                    $item_GA4['item_category'] = $val->name;
                    $item_UA['category'] = $val->name;
                } else {
                    $item_GA4['item_category'.$cat.''] = $val->name;
                    $item_UA['category'.$cat.''] = $val->name;
                }
                foreach( $coupon_product_level as $coupon ) {
                    if(count($coupon->get_product_categories()) > 0) {
                        if(in_array($val->term_id, $coupon->get_product_categories())) {
                            if(!$product_exclude_coupon) {
                                if(is_array($coupon_product_item) && !in_array($coupon->get_code(), $coupon_product_item)){
                                    $coupon_product_item[] = $coupon->get_code();
                                    if($coupon->get_discount_type() == 'percent') {
                                        $discount_product_item += ($productPrice * $coupon->get_amount()) / 100 ;
                                    } else {
                                        $discount_product_item += $coupon->get_amount();
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
                                        $discount_product_item += ($productPrice * $coupon->get_amount()) / 100 ;
                                    } else {
                                        $discount_product_item += $coupon->get_amount();
                                    }
                                }
                            }
                        } else {
                            if (($key = array_search($coupon->get_code(), $coupon_product_item)) !== false) {
                                unset($coupon_product_item[$key]); 
                            }
                            if($coupon->get_discount_type() == 'percent') {
                                $discount_product_item -= ($productPrice * $coupon->get_amount()) / 100 ;
                            } else {
                                $discount_product_item -= $coupon->get_amount();
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
                    $discount_product_item += ($productPrice * $coupon->get_amount()) / 100 ;
                } else {
                    $discount_product_item += $coupon->get_amount();
                }
            }
        }

        $item_GA4['coupon'] = implode(", ", $coupon_product_item);

        if($discount_product_item > 0){
            $item_GA4['discount'] = $discount_product_item;
        }

        $index++;
        array_push($arrayItems, array( 'quantity' => $values['quantity'], 'key' => $item, 'itemGA4' => $item_GA4, 'itemUA' => $item_UA ));
    }

    echo json_encode(array('arrayItems' => $arrayItems));
    
    die();
}