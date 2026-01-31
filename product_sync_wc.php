<?php
/*
Plugin Name: Product Sync WC
Description: Plugin para sincronizar informações de produtos entre sites WooCommerce baseado no SKU.
Version: 0.5
Author: Matheus 
*/
if (!defined('ABSPATH')) exit;

// Verificação do WooCommerce ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit('WooCommerce precisa estar ativo para este plugin funcionar.');
}

//Import log functions
require_once plugin_dir_path(__FILE__) . 'includes' . DIRECTORY_SEPARATOR . 'log_functions.php';

// Inclui o arquivo de funções de sincronização
require_once plugin_dir_path(__FILE__) . 'includes/sync_img_product.php';
// UI do painel admin
require_once plugin_dir_path(__FILE__) . 'includes/admin_ui.php';

// Hook para capturar nome da imagem, se tiver imagem relacionada ao produto.
add_action('pre_post_update', 'capture_image_name_before_update', 10, 2);

// Hook para atualizar imagem ao salvar produto
add_action('save_post_product', 'sync_on_product_save', 10, 3);

// Adiciona o menu do plugin no painel administrativo
add_action('admin_menu', 'add_admin_menu');
// Inicializa as configurações
add_action('admin_init', 'settings_init');
// Enqueue admin styles only on plugin settings page
add_action('admin_enqueue_scripts', 'psm_enqueue_admin_assets');
// Cron
add_filter('cron_schedules', 'psm_add_custom_schedules');
add_action('init', 'psm_schedule_sync_cron');
add_action('psm_sync_cron', 'psm_run_sync');

// Loga criação e edição de produto com base em post_date e post_modified
add_action('save_post_product', 'psm_log_product_crud_timestamps', 10, 3);

function psm_log_product_crud_timestamps($post_ID, $post, $update) {
    if ($post->post_status !== 'publish') return;

    $created = $post->post_date;
    $updated = $post->post_modified;

    log_product_crud_timestamps($post_ID, $created, $updated);
}

function psm_add_custom_schedules($schedules) {
    $frequency = (int) get_option('psm_sync_frequency', 15);
    if ($frequency < 1) $frequency = 15;

    $key = 'psm_every_' . $frequency . '_minutes';
    $schedules[$key] = array(
        'interval' => $frequency * 60,
        'display'  => sprintf(__('Every %d minutes', 'product-sync-wc'), $frequency),
    );

    return $schedules;
}

function psm_schedule_sync_cron() {
    $frequency = (int) get_option('psm_sync_frequency', 15);
    if ($frequency < 1) $frequency = 15;

    $schedule_key = 'psm_every_' . $frequency . '_minutes';

    $current = wp_get_schedule('psm_sync_cron');
    if ($current !== $schedule_key) {
        wp_clear_scheduled_hook('psm_sync_cron');
        wp_schedule_event(time() + 60, $schedule_key, 'psm_sync_cron');
    }
}

function psm_run_sync() {
    if (!get_option('psm_sync_active')) return;
    $sync_products = (int) get_option('psm_sync_products', 1);
    $sync_images = (int) get_option('psm_sync_images', 1);
    $sync_title = (int) get_option('psm_sync_title', 1);
    $sync_price = (int) get_option('psm_sync_price', 0);
    $sync_brands = (int) get_option('psm_sync_brands', 1);
    $sync_brand_images = (int) get_option('psm_sync_brand_images', 1);
    $sync_categories = (int) get_option('psm_sync_categories', 1);
    $sync_category_images = (int) get_option('psm_sync_category_images', 0);

    if (
        !$sync_products
        && !$sync_images
        && !$sync_title
        && !$sync_price
        && !$sync_brands
        && !$sync_brand_images
        && !$sync_categories
        && !$sync_category_images
    ) {
        return;
    }

    if ($sync_images) {
        global $wpdb;
        $batch = 200;
        $offset = 0;

        do {
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT p.ID
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} sku
                        ON sku.post_id = p.ID
                       AND sku.meta_key = '_sku'
                       AND sku.meta_value <> ''
                     WHERE p.post_type = %s
                       AND p.post_status = %s
                     ORDER BY p.ID ASC
                     LIMIT %d OFFSET %d",
                    'product',
                    'publish',
                    $batch,
                    $offset
                )
            );

            if (empty($ids)) break;

            foreach ($ids as $product_id) {
                $post = get_post($product_id);
                if (!$post) continue;
                sync_on_product_save((int) $product_id, $post, true);
            }

            $offset += $batch;
        } while (true);
    }

    do_action(
        'psm_sync_run',
        [
            'products' => $sync_products,
            'images' => $sync_images,
            'title' => $sync_title,
            'price' => $sync_price,
            'brands' => $sync_brands,
            'brand_images' => $sync_brand_images,
            'categories' => $sync_categories,
            'category_images' => $sync_category_images,
        ]
    );
}


