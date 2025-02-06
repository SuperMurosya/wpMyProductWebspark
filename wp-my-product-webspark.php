<?php
/**
 * Plugin Name: WP My Product Webspark
 * Description: Тестова завдання від Камінської М.В.
 * Version: 1.0.0
 * Author: Kaminska M.V.
 */

// Виходимо, якщо WooCommerce не активний
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

class WP_My_Product_Webspark {
    public function __construct() {
        // Реєструємо ендпоінти
        add_action( 'init', [ $this, 'add_custom_endpoints' ] );
        add_action( 'woocommerce_account_add-product_endpoint', [ $this, 'add_product_page' ] );
        add_action( 'woocommerce_account_my-products_endpoint', [ $this, 'my_products_page' ] );
        add_action( 'init', [ $this, 'handle_product_submission' ] );
        add_action( 'init', [ $this, 'handle_product_deletion' ] );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_items' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_media_uploader' ] );

       
        // Очищення пермалінків після активації
        register_activation_hook( __FILE__, [ $this, 'flush_rewrite_rules' ] );
        register_deactivation_hook( __FILE__, [ $this, 'flush_rewrite_rules' ] );
    }

    // Завантаження скриптів для WP Media Uploader
    public function enqueue_media_uploader() {
        wp_enqueue_media();
        wp_enqueue_script( 'wp-my-product-webspark', plugin_dir_url( __FILE__ ) . 'script.js', [ 'jquery' ], null, true );
    }



    // Додаємо кастомні ендпоінти
    public function add_custom_endpoints() {
        add_rewrite_endpoint( 'add-product', EP_ROOT | EP_PAGES );
        add_rewrite_endpoint( 'my-products', EP_ROOT | EP_PAGES );
    }

    // Очищення правил перезапису
    public function flush_rewrite_rules() {
        flush_rewrite_rules();
    }

    // Додаємо пункти меню в My Account
    public function add_menu_items( $items ) {
        $items['add-product'] = 'Add Product';
        $items['my-products'] = 'My Products';
        return $items;
    }

    // Вміст сторінки Add Product
    public function add_product_page() {
        echo '<h2>Додати товар</h2>';
        ?>
        <form method="post">
            <label>Назва товару:</label>
            <input type="text" name="product_name" required>
            <label>Ціна товару:</label>
            <input type="number" name="product_price" required>
            <label>Кількість:</label>
            <input type="number" name="product_quantity" required>
            <label>Опис товару:</label>
            <textarea name="product_description" required></textarea>
            <label>Зображення товару:</label>      

            <input type="hidden" name="product_image" id="product_image">
            <button type="button" id="upload_image_button">Обрати зображення</button>
            <div id="image_preview"></div>


            <button type="submit" name="submit_product">Зберегти</button>
        </form>
        <?php
    }

    // Вміст сторінки My Products
    public function my_products_page() {
        echo '<h2>Мої товари</h2>';
        $current_user = get_current_user_id();
        $paged = max(1, get_query_var('paged'));
        $args = [
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => 10,
            'paged'          => $paged,
            'author'         => $current_user,
        ];
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            echo '<table>
                    <tr>
                        <th>Назва товару</th>
                        <th>Кількість</th>
                        <th>Ціна</th>
                        <th>Статус</th>
                        <th>Дії</th>
                    </tr>';
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $price = get_post_meta($product_id, '_price', true);
                $stock = get_post_meta($product_id, '_stock', true);
                $status = get_post_status($product_id);
                $edit_url = get_edit_post_link($product_id);
                $delete_url = esc_url(add_query_arg(['delete_product' => $product_id]));
                echo '<tr>
                        <td>' . get_the_title() . '</td>
                        <td>' . esc_html($stock) . '</td>
                        <td>' . esc_html($price) . '</td>
                        <td>' . esc_html($status) . '</td>
                        <td>
                            <a href="' . esc_url($edit_url) . '">Редагувати</a>
                            <a href="' . $delete_url . '">Видалити</a>
                        </td>
                      </tr>';
            }
            echo '</table>';
            echo paginate_links([
                'total' => $query->max_num_pages,
            ]);
        } else {
            echo '<p>У вас немає товарів.</p>';
        }
        wp_reset_postdata();
    }

    // Обробка відправки форми для створення товару
    public function handle_product_submission() {
        if ( isset($_POST['submit_product']) ) {
            var_dump($_POST);
            $product_data = [
                'post_title'   => sanitize_text_field( $_POST['product_name'] ),
                'post_content' => sanitize_textarea_field( $_POST['product_description'] ),
                'post_status'  => 'pending',
                'post_type'    => 'product',
            ];
            
            $product_id = wp_insert_post( $product_data );
            
            if ( $product_id ) {
                update_post_meta( $product_id, '_price', sanitize_text_field( $_POST['product_price'] ) );
                update_post_meta( $product_id, '_stock', sanitize_text_field( $_POST['product_quantity'] ) );
                
                // Відправка листа адміністратору
                $admin_email = get_option( 'admin_email' );
                $subject = 'Новий товар на перевірку';
                $message = "Новий товар очікує на перевірку:\n\n";
                $message .= "Назва: " . sanitize_text_field( $_POST['product_name'] ) . "\n";
                $message .= "Автор: " . get_edit_user_link( get_current_user_id() ) . "\n";
                $message .= "Редагування: " . get_edit_post_link( $product_id ) . "\n";
                $headers = array("From: Інтернет магазин ");

                wp_mail( $admin_email, $subject, $message, $headers);
            }
        }
    }

       // Обробка видалення товару
       public function handle_product_deletion() {

        if ( isset($_GET['delete_product']) ) {
            $product_id = intval($_GET['delete_product']);
            if ( get_post_field( 'post_author', $product_id ) == get_current_user_id() ) {
                wp_delete_post( $product_id, true );
                wp_redirect( wc_get_account_endpoint_url( 'my-products' ) );
                exit;
            }
        }
    }
}

new WP_My_Product_Webspark();
