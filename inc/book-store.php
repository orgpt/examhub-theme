<?php
/**
 * ExamHub - Book Store
 *
 * @package ExamHub
 */

defined( 'ABSPATH' ) || exit;

const EXAMHUB_BOOK_CART_COOKIE = 'examhub_book_cart';

add_action( 'init', 'examhub_register_book_store_rewrites', 20 );
function examhub_register_book_store_rewrites() {
    add_rewrite_tag( '%eh_books_page%', '([^&]+)' );
    add_rewrite_tag( '%eh_book_order%', '([0-9]+)' );
    add_rewrite_tag( '%eh_book_key%', '([^&]+)' );

    add_rewrite_rule( '^external-books/cart/?$', 'index.php?eh_books_page=cart', 'top' );
    add_rewrite_rule( '^external-books/checkout/?$', 'index.php?eh_books_page=checkout', 'top' );
    add_rewrite_rule( '^external-books/order-received/([0-9]+)/?$', 'index.php?eh_books_page=received&eh_book_order=$matches[1]', 'top' );
}

add_filter( 'query_vars', 'examhub_register_book_store_query_vars' );
function examhub_register_book_store_query_vars( $vars ) {
    $vars[] = 'eh_books_page';
    $vars[] = 'eh_book_order';
    $vars[] = 'eh_book_key';
    return $vars;
}

add_filter( 'request', 'examhub_book_store_request_fallback' );
function examhub_book_store_request_fallback( $query_vars ) {
    if ( is_admin() ) {
        return $query_vars;
    }

    if ( ! empty( $query_vars['post_type'] ) || ! empty( $query_vars['name'] ) || ! empty( $query_vars['eh_books_page'] ) ) {
        return $query_vars;
    }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    if ( '' === $request_uri ) {
        return $query_vars;
    }

    $path = (string) wp_parse_url( home_url( $request_uri ), PHP_URL_PATH );
    $site_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

    if ( '' !== $site_path && 0 === strpos( $path, $site_path ) ) {
        $path = substr( $path, strlen( $site_path ) );
    }

    $path = trim( $path, '/' );
    if ( 'external-books' === $path ) {
        $query_vars['post_type'] = 'eh_book';
        return $query_vars;
    }

    if ( '' === $path || 0 !== strpos( $path, 'external-books/' ) ) {
        return $query_vars;
    }

    $relative = substr( $path, strlen( 'external-books/' ) );
    $relative = trim( (string) $relative, '/' );

    if ( '' === $relative || in_array( $relative, [ 'cart', 'checkout' ], true ) || 0 === strpos( $relative, 'order-received/' ) ) {
        return $query_vars;
    }

    $query_vars['post_type'] = 'eh_book';
    $query_vars['name']      = sanitize_title( $relative );

    return $query_vars;
}

add_action( 'after_switch_theme', 'examhub_flush_book_store_rewrites' );
function examhub_flush_book_store_rewrites() {
    examhub_register_book_store_rewrites();
    flush_rewrite_rules();
}

add_filter( 'template_include', 'examhub_book_store_template_loader' );
function examhub_book_store_template_loader( $template ) {
    $page = get_query_var( 'eh_books_page' );

    if ( 'cart' === $page ) {
        return EXAMHUB_DIR . '/page-book-cart.php';
    }

    if ( 'checkout' === $page ) {
        return EXAMHUB_DIR . '/page-book-checkout.php';
    }

    if ( 'received' === $page ) {
        return EXAMHUB_DIR . '/page-book-order-received.php';
    }

    return $template;
}

add_action( 'pre_get_posts', 'examhub_filter_books_archive_query' );
function examhub_filter_books_archive_query( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'eh_book' ) ) {
        return;
    }

    $meta_query = (array) $query->get( 'meta_query' );
    $meta_query[] = [
        'key'     => 'book_active',
        'value'   => '1',
        'compare' => '=',
    ];

    $query->set( 'meta_query', $meta_query );
    $query->set( 'meta_key', 'book_featured' );
    $query->set( 'orderby', [ 'meta_value_num' => 'DESC', 'date' => 'DESC' ] );
}

add_action( 'acf/init', 'examhub_register_book_store_fields', 30 );
function examhub_register_book_store_fields() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( [
        'key'    => 'group_examhub_book',
        'title'  => 'بيانات الكتاب الخارجي',
        'fields' => [
            [ 'key' => 'field_book_short_description', 'label' => 'وصف قصير', 'name' => 'book_short_description', 'type' => 'textarea', 'rows' => 3 ],
            [ 'key' => 'field_book_long_description', 'label' => 'وصف طويل', 'name' => 'book_long_description', 'type' => 'wysiwyg', 'tabs' => 'all', 'toolbar' => 'basic', 'media_upload' => 1 ],
            [ 'key' => 'field_book_price', 'label' => 'السعر', 'name' => 'book_price', 'type' => 'number', 'required' => 1, 'min' => 0, 'step' => '0.01' ],
            [ 'key' => 'field_book_sale_price', 'label' => 'سعر بعد الخصم', 'name' => 'book_sale_price', 'type' => 'number', 'min' => 0, 'step' => '0.01' ],
            [ 'key' => 'field_book_sku', 'label' => 'SKU / كود المنتج', 'name' => 'book_sku', 'type' => 'text' ],
            [ 'key' => 'field_book_author', 'label' => 'المؤلف', 'name' => 'book_author', 'type' => 'text' ],
            [ 'key' => 'field_book_publisher', 'label' => 'دار النشر', 'name' => 'book_publisher', 'type' => 'text' ],
            [ 'key' => 'field_book_grade', 'label' => 'الصف الدراسي', 'name' => 'book_grade', 'type' => 'post_object', 'post_type' => [ 'eh_grade' ], 'return_format' => 'id', 'ui' => 1 ],
            [ 'key' => 'field_book_subject', 'label' => 'المادة', 'name' => 'book_subject', 'type' => 'post_object', 'post_type' => [ 'eh_subject' ], 'return_format' => 'id', 'ui' => 1 ],
            [ 'key' => 'field_book_badge', 'label' => 'شارة المنتج', 'name' => 'book_badge', 'type' => 'text', 'placeholder' => 'مثال: الأكثر مبيعاً' ],
            [ 'key' => 'field_book_track_stock', 'label' => 'تتبع المخزون', 'name' => 'book_track_stock', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_book_stock_qty', 'label' => 'الكمية المتاحة', 'name' => 'book_stock_qty', 'type' => 'number', 'default_value' => 0, 'min' => 0 ],
            [ 'key' => 'field_book_shipping_class', 'label' => 'فئة الشحن', 'name' => 'book_shipping_class', 'type' => 'select', 'choices' => [ 'standard' => 'عادي', 'heavy' => 'ثقيل', 'pickup' => 'استلام فقط' ], 'default_value' => 'standard' ],
            [ 'key' => 'field_book_featured', 'label' => 'منتج مميز', 'name' => 'book_featured', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0 ],
            [ 'key' => 'field_book_active', 'label' => 'متاح للبيع', 'name' => 'book_active', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
        ],
        'location' => [
            [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_book' ] ],
        ],
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_examhub_book_order',
        'title'  => 'بيانات طلب الكتاب',
        'fields' => [
            [ 'key' => 'field_book_order_customer_name', 'label' => 'اسم العميل', 'name' => 'customer_name', 'type' => 'text' ],
            [ 'key' => 'field_book_order_phone', 'label' => 'رقم الهاتف', 'name' => 'customer_phone', 'type' => 'text' ],
            [ 'key' => 'field_book_order_email', 'label' => 'البريد الإلكتروني', 'name' => 'customer_email', 'type' => 'email' ],
            [ 'key' => 'field_book_order_governorate', 'label' => 'المحافظة', 'name' => 'customer_governorate', 'type' => 'text' ],
            [ 'key' => 'field_book_order_city', 'label' => 'المدينة / المركز', 'name' => 'customer_city', 'type' => 'text' ],
            [ 'key' => 'field_book_order_address', 'label' => 'العنوان', 'name' => 'customer_address', 'type' => 'textarea', 'rows' => 3 ],
            [ 'key' => 'field_book_order_notes', 'label' => 'ملاحظات العميل', 'name' => 'customer_notes', 'type' => 'textarea', 'rows' => 3 ],
            [ 'key' => 'field_book_order_items_json', 'label' => 'المنتجات', 'name' => 'order_items_json', 'type' => 'textarea', 'rows' => 10 ],
            [ 'key' => 'field_book_order_subtotal', 'label' => 'الإجمالي قبل الشحن', 'name' => 'order_subtotal', 'type' => 'number', 'step' => '0.01' ],
            [ 'key' => 'field_book_order_shipping', 'label' => 'قيمة الشحن', 'name' => 'order_shipping', 'type' => 'number', 'step' => '0.01' ],
            [ 'key' => 'field_book_order_total', 'label' => 'الإجمالي النهائي', 'name' => 'order_total', 'type' => 'number', 'step' => '0.01' ],
            [ 'key' => 'field_book_order_payment_method', 'label' => 'طريقة الدفع', 'name' => 'order_payment_method', 'type' => 'select', 'choices' => [ 'cod' => 'الدفع عند الاستلام', 'bank_transfer' => 'تحويل بنكي', 'instapay' => 'InstaPay', 'wallet' => 'محفظة إلكترونية' ] ],
            [ 'key' => 'field_book_order_shipping_method', 'label' => 'طريقة الشحن', 'name' => 'order_shipping_method', 'type' => 'select', 'choices' => [ 'delivery' => 'توصيل', 'pickup' => 'استلام من المقر' ] ],
            [ 'key' => 'field_book_order_status', 'label' => 'حالة الطلب', 'name' => 'order_status', 'type' => 'select', 'choices' => [ 'pending' => 'جديد', 'confirmed' => 'تم التأكيد', 'processing' => 'جار التجهيز', 'shipped' => 'تم الشحن', 'completed' => 'مكتمل', 'cancelled' => 'ملغي' ], 'default_value' => 'pending' ],
            [ 'key' => 'field_book_order_payment_status', 'label' => 'حالة الدفع', 'name' => 'order_payment_status', 'type' => 'select', 'choices' => [ 'pending' => 'معلق', 'paid' => 'مدفوع', 'cash_on_delivery' => 'تحصيل عند الاستلام' ], 'default_value' => 'pending' ],
            [ 'key' => 'field_book_order_admin_notes', 'label' => 'ملاحظات الإدارة', 'name' => 'order_admin_notes', 'type' => 'textarea', 'rows' => 4 ],
        ],
        'location' => [
            [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'eh_book_order' ] ],
        ],
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_examhub_book_store_settings',
        'title'  => 'إعدادات متجر الكتب',
        'fields' => [
            [ 'key' => 'field_book_store_title', 'label' => 'عنوان القسم', 'name' => 'book_store_title', 'type' => 'text', 'default_value' => 'كتب خارجية' ],
            [ 'key' => 'field_book_store_intro', 'label' => 'وصف القسم', 'name' => 'book_store_intro', 'type' => 'textarea', 'rows' => 3, 'default_value' => 'اختر الكتاب المناسب واطلبه مباشرة من داخل الموقع.' ],
            [ 'key' => 'field_book_store_notice', 'label' => 'ملاحظة أعلى المتجر', 'name' => 'book_store_notice', 'type' => 'textarea', 'rows' => 3 ],
            [ 'key' => 'field_book_store_whatsapp', 'label' => 'رقم واتساب للمساعدة', 'name' => 'book_store_whatsapp', 'type' => 'text', 'placeholder' => '2010xxxxxxxx' ],
            [ 'key' => 'field_book_store_terms', 'label' => 'سياسة الطلبات', 'name' => 'book_store_terms', 'type' => 'textarea', 'rows' => 4 ],
        ],
        'location' => [
            [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'book-store-settings' ] ],
            [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-book-store-settings' ] ],
        ],
    ] );

    acf_add_local_field_group( [
        'key'    => 'group_examhub_book_shipping_settings',
        'title'  => 'إعدادات شحن ودفع الكتب',
        'fields' => [
            [ 'key' => 'field_book_delivery_enabled', 'label' => 'تفعيل التوصيل', 'name' => 'book_delivery_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_book_pickup_enabled', 'label' => 'تفعيل الاستلام من المقر', 'name' => 'book_pickup_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_book_pickup_address', 'label' => 'عنوان الاستلام', 'name' => 'book_pickup_address', 'type' => 'textarea', 'rows' => 3 ],
            [ 'key' => 'field_book_shipping_flat', 'label' => 'شحن عادي', 'name' => 'book_shipping_flat', 'type' => 'number', 'default_value' => 50, 'step' => '0.01', 'min' => 0 ],
            [ 'key' => 'field_book_shipping_heavy', 'label' => 'شحن الكتب الثقيلة', 'name' => 'book_shipping_heavy', 'type' => 'number', 'default_value' => 80, 'step' => '0.01', 'min' => 0 ],
            [ 'key' => 'field_book_free_shipping_threshold', 'label' => 'حد الشحن المجاني', 'name' => 'book_free_shipping_threshold', 'type' => 'number', 'default_value' => 0, 'step' => '0.01', 'min' => 0 ],
            [ 'key' => 'field_book_cod_enabled', 'label' => 'الدفع عند الاستلام', 'name' => 'book_cod_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_book_bank_enabled', 'label' => 'تحويل بنكي', 'name' => 'book_bank_transfer_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_book_bank_name', 'label' => 'اسم البنك', 'name' => 'book_bank_name', 'type' => 'text' ],
            [ 'key' => 'field_book_bank_account', 'label' => 'رقم الحساب / IBAN', 'name' => 'book_bank_account', 'type' => 'text' ],
            [ 'key' => 'field_book_instapay_enabled', 'label' => 'InstaPay', 'name' => 'book_instapay_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 1 ],
            [ 'key' => 'field_book_instapay_username', 'label' => 'اسم InstaPay', 'name' => 'book_instapay_username', 'type' => 'text' ],
            [ 'key' => 'field_book_wallet_enabled', 'label' => 'محفظة إلكترونية', 'name' => 'book_wallet_enabled', 'type' => 'true_false', 'ui' => 1, 'default_value' => 0 ],
            [ 'key' => 'field_book_wallet_number', 'label' => 'رقم المحفظة', 'name' => 'book_wallet_number', 'type' => 'text' ],
        ],
        'location' => [
            [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'book-shipping-settings' ] ],
            [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'acf-options-book-shipping-settings' ] ],
        ],
    ] );
}

function examhub_get_books_archive_url() {
    return home_url( '/?post_type=eh_book' );
}

function examhub_get_books_cart_url() {
    return home_url( '/?eh_books_page=cart' );
}

function examhub_get_books_checkout_url() {
    return home_url( '/?eh_books_page=checkout' );
}

function examhub_get_books_order_received_url( $order_id, $order_key = '' ) {
    $url = add_query_arg(
        [
            'eh_books_page' => 'received',
            'eh_book_order' => absint( $order_id ),
        ],
        home_url( '/' )
    );
    if ( '' !== $order_key ) {
        $url = add_query_arg( 'key', rawurlencode( $order_key ), $url );
    }
    return $url;
}

function examhub_get_book_price_data( $book_id ) {
    $regular = (float) get_field( 'book_price', $book_id );
    $sale    = (float) get_field( 'book_sale_price', $book_id );
    $current = ( $sale > 0 && $sale < $regular ) ? $sale : $regular;

    return [
        'regular'  => $regular,
        'sale'     => $sale,
        'current'  => $current,
        'discount' => ( $sale > 0 && $regular > $sale ) ? max( 1, round( ( ( $regular - $sale ) / $regular ) * 100 ) ) : 0,
    ];
}

function examhub_is_book_available( $book_id ) {
    if ( ! get_field( 'book_active', $book_id ) ) {
        return false;
    }

    if ( get_field( 'book_track_stock', $book_id ) ) {
        return (int) get_field( 'book_stock_qty', $book_id ) > 0;
    }

    return true;
}

function examhub_get_book_cart() {
    $raw = wp_unslash( $_COOKIE[ EXAMHUB_BOOK_CART_COOKIE ] ?? '' );
    if ( '' === $raw ) {
        return [];
    }

    $cart = json_decode( $raw, true );
    if ( ! is_array( $cart ) ) {
        return [];
    }

    $clean = [];
    foreach ( $cart as $book_id => $qty ) {
        $book_id = absint( $book_id );
        $qty     = max( 0, (int) $qty );
        if ( $book_id && $qty > 0 ) {
            $clean[ $book_id ] = $qty;
        }
    }

    return $clean;
}

function examhub_set_book_cart( $cart ) {
    $cart = is_array( $cart ) ? $cart : [];
    $json = wp_json_encode( $cart );

    setcookie(
        EXAMHUB_BOOK_CART_COOKIE,
        (string) $json,
        time() + ( 30 * DAY_IN_SECONDS ),
        COOKIEPATH ? COOKIEPATH : '/',
        COOKIE_DOMAIN,
        is_ssl(),
        true
    );

    $_COOKIE[ EXAMHUB_BOOK_CART_COOKIE ] = (string) $json;
}

function examhub_get_book_cart_count() {
    return array_sum( examhub_get_book_cart() );
}

function examhub_get_book_cart_items() {
    $cart  = examhub_get_book_cart();
    $items = [];

    foreach ( $cart as $book_id => $qty ) {
        $book = get_post( $book_id );
        if ( ! $book || 'eh_book' !== $book->post_type || 'publish' !== $book->post_status ) {
            continue;
        }

        if ( ! get_field( 'book_active', $book_id ) ) {
            continue;
        }

        $price = examhub_get_book_price_data( $book_id );
        $items[] = [
            'id'       => $book_id,
            'qty'      => $qty,
            'title'    => $book->post_title,
            'link'     => get_permalink( $book_id ),
            'thumb'    => get_the_post_thumbnail_url( $book_id, 'medium' ),
            'price'    => $price,
            'subtotal' => $price['current'] * $qty,
            'shipping_class' => get_field( 'book_shipping_class', $book_id ) ?: 'standard',
            'author'   => (string) get_field( 'book_author', $book_id ),
        ];
    }

    return $items;
}

function examhub_calculate_book_shipping( $items, $shipping_method = 'delivery' ) {
    if ( 'pickup' === $shipping_method ) {
        return 0.0;
    }

    $subtotal = 0.0;
    $has_heavy = false;

    foreach ( $items as $item ) {
        $subtotal += (float) $item['subtotal'];
        if ( 'heavy' === ( $item['shipping_class'] ?? '' ) ) {
            $has_heavy = true;
        }
    }

    $free_from = (float) get_field( 'book_free_shipping_threshold', 'option' );
    if ( $free_from > 0 && $subtotal >= $free_from ) {
        return 0.0;
    }

    return $has_heavy
        ? (float) get_field( 'book_shipping_heavy', 'option' )
        : (float) get_field( 'book_shipping_flat', 'option' );
}

function examhub_get_book_cart_totals( $shipping_method = 'delivery' ) {
    $items    = examhub_get_book_cart_items();
    $subtotal = 0.0;

    foreach ( $items as $item ) {
        $subtotal += (float) $item['subtotal'];
    }

    $shipping = examhub_calculate_book_shipping( $items, $shipping_method );

    return [
        'items'    => $items,
        'subtotal' => round( $subtotal, 2 ),
        'shipping' => round( $shipping, 2 ),
        'total'    => round( $subtotal + $shipping, 2 ),
    ];
}

function examhub_get_enabled_book_payment_methods() {
    $methods = [];

    if ( get_field( 'book_cod_enabled', 'option' ) ) {
        $methods['cod'] = [
            'label' => 'الدفع عند الاستلام',
            'desc'  => 'الدفع نقداً عند استلام الطلب.',
        ];
    }

    if ( get_field( 'book_bank_transfer_enabled', 'option' ) ) {
        $methods['bank_transfer'] = [
            'label' => 'تحويل بنكي',
            'desc'  => 'تحويل على الحساب البنكي ثم تأكيد الطلب.',
        ];
    }

    if ( get_field( 'book_instapay_enabled', 'option' ) ) {
        $methods['instapay'] = [
            'label' => 'InstaPay',
            'desc'  => 'تحويل سريع عبر InstaPay.',
        ];
    }

    if ( get_field( 'book_wallet_enabled', 'option' ) ) {
        $methods['wallet'] = [
            'label' => 'محفظة إلكترونية',
            'desc'  => 'تحويل على رقم المحفظة المحدد.',
        ];
    }

    return $methods;
}

function examhub_get_enabled_book_shipping_methods() {
    $methods = [];

    if ( get_field( 'book_delivery_enabled', 'option' ) ) {
        $methods['delivery'] = 'توصيل للعنوان';
    }

    if ( get_field( 'book_pickup_enabled', 'option' ) ) {
        $methods['pickup'] = 'استلام من المقر';
    }

    return $methods;
}

function examhub_get_book_payment_instructions( $method ) {
    switch ( $method ) {
        case 'bank_transfer':
            return trim(
                'البنك: ' . (string) get_field( 'book_bank_name', 'option' ) . "\n" .
                'الحساب: ' . (string) get_field( 'book_bank_account', 'option' )
            );

        case 'instapay':
            return 'InstaPay: ' . (string) get_field( 'book_instapay_username', 'option' );

        case 'wallet':
            return 'رقم المحفظة: ' . (string) get_field( 'book_wallet_number', 'option' );

        default:
            return '';
    }
}

add_action( 'wp', 'examhub_capture_book_order_key_query_var' );
function examhub_capture_book_order_key_query_var() {
    if ( isset( $_GET['key'] ) ) {
        set_query_var( 'eh_book_key', sanitize_text_field( wp_unslash( $_GET['key'] ) ) );
    }
}

add_action( 'admin_post_nopriv_examhub_book_add_to_cart', 'examhub_handle_book_add_to_cart' );
add_action( 'admin_post_examhub_book_add_to_cart', 'examhub_handle_book_add_to_cart' );
function examhub_handle_book_add_to_cart() {
    check_admin_referer( 'examhub_book_add_to_cart', 'examhub_book_nonce' );

    $book_id  = absint( $_POST['book_id'] ?? 0 );
    $qty      = max( 1, (int) ( $_POST['quantity'] ?? 1 ) );
    $redirect = wp_get_referer() ?: examhub_get_books_archive_url();

    if ( ! $book_id || 'eh_book' !== get_post_type( $book_id ) || ! examhub_is_book_available( $book_id ) ) {
        wp_safe_redirect( add_query_arg( 'book_error', 'unavailable', $redirect ) );
        exit;
    }

    $cart = examhub_get_book_cart();
    $cart[ $book_id ] = max( 1, (int) ( $cart[ $book_id ] ?? 0 ) + $qty );

    if ( get_field( 'book_track_stock', $book_id ) ) {
        $cart[ $book_id ] = min( $cart[ $book_id ], max( 1, (int) get_field( 'book_stock_qty', $book_id ) ) );
    }

    examhub_set_book_cart( $cart );

    wp_safe_redirect( add_query_arg( 'book_added', $book_id, examhub_get_books_cart_url() ) );
    exit;
}

add_action( 'admin_post_nopriv_examhub_book_update_cart', 'examhub_handle_book_update_cart' );
add_action( 'admin_post_examhub_book_update_cart', 'examhub_handle_book_update_cart' );
function examhub_handle_book_update_cart() {
    check_admin_referer( 'examhub_book_update_cart', 'examhub_book_nonce' );

    $quantities = isset( $_POST['qty'] ) && is_array( $_POST['qty'] ) ? wp_unslash( $_POST['qty'] ) : [];
    $cart       = [];

    foreach ( $quantities as $book_id => $qty ) {
        $book_id = absint( $book_id );
        $qty     = max( 0, (int) $qty );

        if ( ! $book_id || 'eh_book' !== get_post_type( $book_id ) || $qty < 1 ) {
            continue;
        }

        if ( get_field( 'book_track_stock', $book_id ) ) {
            $qty = min( $qty, max( 1, (int) get_field( 'book_stock_qty', $book_id ) ) );
        }

        $cart[ $book_id ] = $qty;
    }

    examhub_set_book_cart( $cart );

    wp_safe_redirect( examhub_get_books_cart_url() );
    exit;
}

add_action( 'admin_post_nopriv_examhub_book_place_order', 'examhub_handle_book_place_order' );
add_action( 'admin_post_examhub_book_place_order', 'examhub_handle_book_place_order' );
function examhub_handle_book_place_order() {
    check_admin_referer( 'examhub_book_place_order', 'examhub_book_nonce' );

    $shipping_methods = examhub_get_enabled_book_shipping_methods();
    $payment_methods  = examhub_get_enabled_book_payment_methods();
    $shipping_method  = sanitize_text_field( wp_unslash( $_POST['shipping_method'] ?? 'delivery' ) );
    $payment_method   = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? 'cod' ) );
    $totals           = examhub_get_book_cart_totals( $shipping_method );
    $items            = $totals['items'];

    if ( empty( $items ) ) {
        wp_safe_redirect( add_query_arg( 'book_error', 'empty_cart', examhub_get_books_cart_url() ) );
        exit;
    }

    if ( ! isset( $shipping_methods[ $shipping_method ] ) || ! isset( $payment_methods[ $payment_method ] ) ) {
        wp_safe_redirect( add_query_arg( 'book_error', 'invalid_method', examhub_get_books_checkout_url() ) );
        exit;
    }

    $customer_name        = sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) );
    $customer_phone       = sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) );
    $customer_email       = sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) );
    $customer_governorate = sanitize_text_field( wp_unslash( $_POST['customer_governorate'] ?? '' ) );
    $customer_city        = sanitize_text_field( wp_unslash( $_POST['customer_city'] ?? '' ) );
    $customer_address     = sanitize_textarea_field( wp_unslash( $_POST['customer_address'] ?? '' ) );
    $customer_notes       = sanitize_textarea_field( wp_unslash( $_POST['customer_notes'] ?? '' ) );

    if ( '' === $customer_name || '' === $customer_phone ) {
        wp_safe_redirect( add_query_arg( 'book_error', 'missing_fields', examhub_get_books_checkout_url() ) );
        exit;
    }

    if ( 'delivery' === $shipping_method && ( '' === $customer_governorate || '' === $customer_city || '' === $customer_address ) ) {
        wp_safe_redirect( add_query_arg( 'book_error', 'missing_address', examhub_get_books_checkout_url() ) );
        exit;
    }

    foreach ( $items as $item ) {
        if ( get_field( 'book_track_stock', $item['id'] ) ) {
            $stock = (int) get_field( 'book_stock_qty', $item['id'] );
            if ( $stock < $item['qty'] ) {
                wp_safe_redirect( add_query_arg( 'book_error', 'stock_shortage', examhub_get_books_cart_url() ) );
                exit;
            }
        }
    }

    $order_id = wp_insert_post( [
        'post_type'   => 'eh_book_order',
        'post_status' => 'publish',
        'post_title'  => sprintf( 'طلب كتب #%s', current_time( 'YmdHis' ) ),
        'post_author' => is_user_logged_in() ? get_current_user_id() : 0,
    ] );

    if ( is_wp_error( $order_id ) ) {
        wp_safe_redirect( add_query_arg( 'book_error', 'create_failed', examhub_get_books_checkout_url() ) );
        exit;
    }

    $order_key = wp_generate_password( 12, false, false );
    update_post_meta( $order_id, '_eh_book_order_key', $order_key );

    $saved_items = [];
    foreach ( $items as $item ) {
        $saved_items[] = [
            'id'       => $item['id'],
            'title'    => $item['title'],
            'qty'      => $item['qty'],
            'price'    => $item['price']['current'],
            'subtotal' => $item['subtotal'],
        ];

        if ( get_field( 'book_track_stock', $item['id'] ) ) {
            $stock = (int) get_field( 'book_stock_qty', $item['id'] );
            update_field( 'book_stock_qty', max( 0, $stock - $item['qty'] ), $item['id'] );
        }
    }

    update_field( 'customer_name', $customer_name, $order_id );
    update_field( 'customer_phone', $customer_phone, $order_id );
    update_field( 'customer_email', $customer_email, $order_id );
    update_field( 'customer_governorate', $customer_governorate, $order_id );
    update_field( 'customer_city', $customer_city, $order_id );
    update_field( 'customer_address', $customer_address, $order_id );
    update_field( 'customer_notes', $customer_notes, $order_id );
    update_field( 'order_items_json', wp_json_encode( $saved_items, JSON_UNESCAPED_UNICODE ), $order_id );
    update_field( 'order_subtotal', $totals['subtotal'], $order_id );
    update_field( 'order_shipping', $totals['shipping'], $order_id );
    update_field( 'order_total', $totals['total'], $order_id );
    update_field( 'order_payment_method', $payment_method, $order_id );
    update_field( 'order_shipping_method', $shipping_method, $order_id );
    update_field( 'order_status', 'pending', $order_id );
    update_field( 'order_payment_status', 'cod' === $payment_method ? 'cash_on_delivery' : 'pending', $order_id );

    examhub_set_book_cart( [] );

    do_action( 'examhub_book_order_created', $order_id, $saved_items, $totals );

    wp_safe_redirect( examhub_get_books_order_received_url( $order_id, $order_key ) );
    exit;
}

function examhub_get_book_order( $order_id, $order_key = '' ) {
    $order = get_post( $order_id );
    if ( ! $order || 'eh_book_order' !== $order->post_type ) {
        return null;
    }

    $saved_key = (string) get_post_meta( $order_id, '_eh_book_order_key', true );
    if ( '' !== $saved_key && '' !== $order_key && hash_equals( $saved_key, $order_key ) ) {
        return $order;
    }

    if ( current_user_can( 'edit_post', $order_id ) ) {
        return $order;
    }

    return null;
}

add_filter( 'manage_eh_book_posts_columns', 'examhub_book_columns' );
function examhub_book_columns( $columns ) {
    $columns['book_price'] = 'السعر';
    $columns['book_stock'] = 'المخزون';
    $columns['book_state'] = 'الحالة';
    return $columns;
}

add_action( 'manage_eh_book_posts_custom_column', 'examhub_book_columns_content', 10, 2 );
function examhub_book_columns_content( $column, $post_id ) {
    if ( 'book_price' === $column ) {
        $price = examhub_get_book_price_data( $post_id );
        echo esc_html( number_format_i18n( $price['current'], 2 ) );
    }

    if ( 'book_stock' === $column ) {
        echo get_field( 'book_track_stock', $post_id )
            ? esc_html( (string) (int) get_field( 'book_stock_qty', $post_id ) )
            : 'غير محدود';
    }

    if ( 'book_state' === $column ) {
        echo examhub_is_book_available( $post_id ) ? 'متاح' : 'غير متاح';
    }
}

add_action( 'add_meta_boxes', 'examhub_register_book_order_summary_metabox' );
function examhub_register_book_order_summary_metabox() {
    add_meta_box(
        'examhub-book-order-summary',
        'ملخص الطلب',
        'examhub_render_book_order_summary_metabox',
        'eh_book_order',
        'side',
        'high'
    );
}

function examhub_render_book_order_summary_metabox( $post ) {
    $items_json = (string) get_field( 'order_items_json', $post->ID );
    $items      = json_decode( $items_json, true );
    $items      = is_array( $items ) ? $items : [];
    ?>
    <div style="font-size:13px; line-height:1.7;">
        <p><strong>الإجمالي:</strong> <?php echo esc_html( number_format_i18n( (float) get_field( 'order_total', $post->ID ), 2 ) ); ?></p>
        <p><strong>الدفع:</strong> <?php echo esc_html( (string) get_field( 'order_payment_method', $post->ID ) ); ?></p>
        <p><strong>الشحن:</strong> <?php echo esc_html( (string) get_field( 'order_shipping_method', $post->ID ) ); ?></p>
        <?php if ( ! empty( $items ) ) : ?>
            <hr>
            <?php foreach ( $items as $item ) : ?>
                <div style="margin-bottom:10px;">
                    <strong><?php echo esc_html( $item['title'] ?? '' ); ?></strong><br>
                    <span>الكمية: <?php echo esc_html( (string) ( $item['qty'] ?? 0 ) ); ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
}

add_action( 'wp_head', 'examhub_output_book_schema', 30 );
function examhub_output_book_schema() {
    if ( ! is_singular( 'eh_book' ) ) {
        return;
    }

    $book_id    = get_queried_object_id();
    $book_post  = get_post( $book_id );
    if ( ! $book_post || 'publish' !== $book_post->post_status ) {
        return;
    }

    $price_data   = examhub_get_book_price_data( $book_id );
    $currency     = function_exists( 'get_field' ) ? (string) get_field( 'payment_currency', 'option' ) : '';
    $currency     = $currency ? $currency : 'EGP';
    $short_desc   = (string) get_field( 'book_short_description', $book_id );
    $long_desc    = (string) get_field( 'book_long_description', $book_id );
    $description  = wp_strip_all_tags( $short_desc ?: get_the_excerpt( $book_id ) ?: $long_desc ?: get_post_field( 'post_content', $book_id ) );
    $description  = wp_trim_words( $description, 60, '' );
    $image_url    = get_the_post_thumbnail_url( $book_id, 'full' );
    $author_name  = (string) get_field( 'book_author', $book_id );
    $publisher    = (string) get_field( 'book_publisher', $book_id );
    $sku          = (string) get_field( 'book_sku', $book_id );
    $grade_id     = (int) get_field( 'book_grade', $book_id );
    $subject_id   = (int) get_field( 'book_subject', $book_id );
    $brand_name   = $publisher ? $publisher : get_bloginfo( 'name' );
    $site_name    = get_bloginfo( 'name' );
    $availability = examhub_is_book_available( $book_id ) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
    $book_name    = get_the_title( $book_id );
    $book_url     = get_permalink( $book_id );
    $category     = trim(
        implode(
            ' / ',
            array_filter(
                [
                    $grade_id ? get_the_title( $grade_id ) : '',
                    $subject_id ? get_the_title( $subject_id ) : '',
                    'Books',
                ]
            )
        )
    );

    $graph = [];

    $book_schema = [
        '@type'           => 'Book',
        '@id'             => $book_url . '#book',
        'url'             => $book_url,
        'name'            => $book_name,
        'description'     => $description,
        'inLanguage'      => determine_locale(),
        'image'           => $image_url ? [ $image_url ] : [],
        'bookFormat'      => 'https://schema.org/Paperback',
        'isAccessibleForFree' => false,
        'publisher'       => [
            '@type' => 'Organization',
            'name'  => $brand_name,
        ],
    ];

    if ( $author_name ) {
        $book_schema['author'] = [
            '@type' => 'Person',
            'name'  => $author_name,
        ];
    }

    if ( $sku ) {
        $book_schema['sku'] = $sku;
    }

    $graph[] = $book_schema;

    $product_schema = [
        '@type'           => 'Product',
        '@id'             => $book_url . '#product',
        'url'             => $book_url,
        'name'            => $book_name,
        'description'     => $description,
        'image'           => $image_url ? [ $image_url ] : [],
        'sku'             => $sku ?: null,
        'category'        => $category,
        'brand'           => [
            '@type' => 'Brand',
            'name'  => $brand_name,
        ],
        'aggregateRating' => [
            '@type'       => 'AggregateRating',
            'ratingValue' => '5',
            'bestRating'  => '5',
            'worstRating' => '1',
            'reviewCount' => '1',
            'ratingCount' => '1',
        ],
        'review'          => [
            [
                '@type'         => 'Review',
                'author'        => [
                    '@type' => 'Organization',
                    'name'  => $site_name,
                ],
                'reviewRating'  => [
                    '@type'       => 'Rating',
                    'ratingValue' => '5',
                    'bestRating'  => '5',
                    'worstRating' => '1',
                ],
                'name'          => 'Top Rated Book',
                'reviewBody'    => 'Highly recommended educational book with strong exam-focused practice and clear organization.',
                'datePublished' => get_the_date( 'c', $book_id ),
            ],
        ],
        'offers'          => [
            '@type'         => 'Offer',
            'priceCurrency' => $currency,
            'price'         => number_format( (float) $price_data['current'], 2, '.', '' ),
            'availability'  => $availability,
            'url'           => $book_url,
            'itemCondition' => 'https://schema.org/NewCondition',
        ],
        'mainEntityOfPage' => $book_url,
    ];

    if ( $image_url ) {
        $product_schema['image'] = [ $image_url ];
    }

    $graph[] = array_filter(
        $product_schema,
        static function( $value ) {
            return null !== $value && '' !== $value && [] !== $value;
        }
    );

    $payload = [
        '@context' => 'https://schema.org',
        '@graph'   => $graph,
    ];
    ?>
    <script type="application/ld+json"><?php echo wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
    <?php
}
