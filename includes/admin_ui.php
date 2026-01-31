<?php
if (!defined('ABSPATH')) exit;

function add_admin_menu() {
    add_menu_page(
        'Configurações do Product Sync WC',
        'Product Sync WC',
        'manage_options',
        'product_sync_wc',
        'settings_page',
        'dashicons-update'
    );
}

function settings_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Configurações do Product Sync WC', 'product-sync-wc'); ?></h1>
        <hr>

        <form method="post" action="options.php">
            <?php
                settings_fields('psm_options_group');
                do_settings_sections('product_sync_wc');
            ?>

            <!-- ====== SEÇÃO 2: Sync Settings (versão visual parecida com o HTML antigo) ====== -->
            <div class="psm-settings">
                <div class="psm-sync-settings">
                    <div class="psm-left">
                        <div class="psm-sync-intro"><?php echo esc_html__('What should be synced ? Since SKU matches', 'product-sync-wc'); ?></div>

                        <div class="psm-sync-list">
                            <label class="psm-field">
                                <input type="hidden" name="psm_sync_products" value="0" />
                                <input type="checkbox" name="psm_sync_products" value="1" <?php checked(1, get_option('psm_sync_products', 1)); ?> />
                                <span><?php echo esc_html__('Products', 'product-sync-wc'); ?></span>
                            </label>

                            <div class="psm-sublist">
                                <label class="psm-field">
                                    <input type="hidden" name="psm_sync_images" value="0" />
                                    <input type="checkbox" name="psm_sync_images" value="1" <?php checked(1, get_option('psm_sync_images', 1)); ?> />
                                    <span><?php echo esc_html__('Products Images', 'product-sync-wc'); ?></span>
                                </label>

                                <label class="psm-field">
                                    <input type="hidden" name="psm_sync_title" value="0" />
                                    <input type="checkbox" name="psm_sync_title" value="1" <?php checked(1, get_option('psm_sync_title', 1)); ?> />
                                    <span><?php echo esc_html__('Products Title', 'product-sync-wc'); ?></span>
                                </label>

                                <label class="psm-field">
                                    <input type="hidden" name="psm_sync_price" value="0" />
                                    <input type="checkbox" name="psm_sync_price" value="1" <?php checked(1, get_option('psm_sync_price', 0)); ?> />
                                    <span><?php echo esc_html__('Products Price', 'product-sync-wc'); ?></span>
                                </label>
                            </div>

                            <label class="psm-field" style="margin-top:6px;">
                                <input type="hidden" name="psm_sync_brands" value="0" />
                                <input type="checkbox" name="psm_sync_brands" value="1" <?php checked(1, get_option('psm_sync_brands', 1)); ?> />
                                <span><?php echo esc_html__('Brands', 'product-sync-wc'); ?></span>
                            </label>

                            <div class="psm-sublist">
                                <label class="psm-field">
                                    <input type="hidden" name="psm_sync_brand_images" value="0" />
                                    <input type="checkbox" name="psm_sync_brand_images" value="1" <?php checked(1, get_option('psm_sync_brand_images', 1)); ?> />
                                    <span><?php echo esc_html__('Brands Images', 'product-sync-wc'); ?></span>
                                </label>
                            </div>

                            <label class="psm-field" style="margin-top:6px;">
                                <input type="hidden" name="psm_sync_categories" value="0" />
                                <input type="checkbox" name="psm_sync_categories" value="1" <?php checked(1, get_option('psm_sync_categories', 1)); ?> />
                                <span><?php echo esc_html__('Categories', 'product-sync-wc'); ?></span>
                            </label>

                            <div class="psm-sublist">
                                <label class="psm-field">
                                    <input type="hidden" name="psm_sync_category_images" value="0" />
                                    <input type="checkbox" name="psm_sync_category_images" value="1" <?php checked(1, get_option('psm_sync_category_images', 0)); ?> />
                                    <span><?php echo esc_html__('Categories Images', 'product-sync-wc'); ?></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="psm-right">
                        <div class="psm-row" style="margin-top:34px;">
                            <span><?php echo esc_html__('Sync frequency', 'product-sync-wc'); ?></span>
                            <?php psm_render_select_frequency(); ?>
                        </div>

                        <div class="psm-row" style="margin-top:18px;">
                            <span title="Feature in development."><del><?php echo esc_html__('Create Missing', 'product-sync-wc'); ?></del></span>
                            <label class="psm-toggle" aria-disabled="true" title="Feature in development.">
                                <input type="hidden" name="psm_create_missing" value="0" />
                                <input type="checkbox" name="psm_create_missing" value="1" <?php checked(1, get_option('psm_create_missing', 0)); ?> disabled />
                                <span class="psm-toggle-track" aria-hidden="true"></span>
                                <span class="psm-toggle-knob" aria-hidden="true"></span>
                            </label>
                        </div>

                        <div class="psm-row" style="margin-top:22px;">
                            <span><?php echo esc_html__('Activate SYNC', 'product-sync-wc'); ?></span>
                            <label class="psm-toggle">
                                <input type="hidden" name="psm_sync_active" value="0" />
                                <input type="checkbox" name="psm_sync_active" value="1" <?php checked(1, get_option('psm_sync_active', 0)); ?> />
                                <span class="psm-toggle-track" aria-hidden="true"></span>
                                <span class="psm-toggle-knob" aria-hidden="true"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ====== BOTÃO SALVAR CONFIGS ====== -->
                <div class="psm-save">
                    <?php submit_button(__('Save Configs', 'product-sync-wc')); ?>
                </div>

                <!-- ====== LOGS / TABELA ====== -->
                <div class="psm-logs">
                    <h2 class="psm-logs-title"><?php esc_html_e('LOGS', 'product-sync-wc'); ?></h2>
                    <table class="psm-logs-table widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('DATE / TIME', 'product-sync-wc'); ?></th>
                                <th><?php esc_html_e('TYPE', 'product-sync-wc'); ?></th>
                                <th><?php esc_html_e('SKU', 'product-sync-wc'); ?></th>
                                <th><?php esc_html_e('STATUS / ACTION', 'product-sync-wc'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo esc_html__('a', 'product-sync-wc'); ?></td>
                                <td><?php echo esc_html__('b', 'product-sync-wc'); ?></td>
                                <td><?php echo esc_html__('c', 'product-sync-wc'); ?></td>
                                <td><?php echo esc_html__('d', 'product-sync-wc'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>
    <?php
}

function psm_render_checkbox($option_name, $label) {
    $val = get_option($option_name, 0);
    printf(
        '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
        esc_attr($option_name),
        checked(1, $val, false),
        esc_html($label)
    );
}

function psm_render_select_frequency() {
    $val = get_option('psm_sync_frequency', '15');
    $options = array('1' => '1 min (Test)', '5' => '5 min', '10' => '10 min', '15' => '15 min', '20' => '20 min', '30' => '30 min');
    echo '<select name="psm_sync_frequency">';
    foreach ($options as $k => $label) {
        printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($k, $val, false), esc_html($label));
    }
    echo '</select>';
}

function username_render() {
    $username = get_option('psm_username');
    echo "<input type='text' name='psm_username' value='" . esc_attr($username) . "' />";
}

function password_render() {
    $password = get_option('psm_password');
    echo "<input type='password' name='psm_password' value='" . esc_attr($password) . "' />";
}

function url_render() {
    $url = get_option('psm_url');
    echo "<input type='url' name='psm_url' value='" . esc_attr($url) . "' />";
}

function psm_enqueue_admin_assets($hook) {
    // only load on our plugin settings page
    if ($hook !== 'toplevel_page_product_sync_wc') return;
    wp_enqueue_style('psm-admin-css', plugin_dir_url(__FILE__) . '../assets/css/admin.css', array(), '0.5');
}

function settings_init() {
    // campos já existentes
    register_setting('psm_options_group', 'psm_username', 'sanitize_text_field');
    register_setting('psm_options_group', 'psm_password', 'sanitize_text_field');
    register_setting('psm_options_group', 'psm_url', 'esc_url_raw');

    // novos campos de sync
    register_setting('psm_options_group', 'psm_sync_products', 'absint');
    register_setting('psm_options_group', 'psm_sync_images', 'absint');
    register_setting('psm_options_group', 'psm_sync_title', 'absint');
    register_setting('psm_options_group', 'psm_sync_price', 'absint');
    register_setting('psm_options_group', 'psm_sync_brands', 'absint');
    register_setting('psm_options_group', 'psm_sync_brand_images', 'absint');
    register_setting('psm_options_group', 'psm_sync_categories', 'absint');
    register_setting('psm_options_group', 'psm_sync_category_images', 'absint');
    register_setting('psm_options_group', 'psm_sync_frequency', 'sanitize_text_field');
    register_setting('psm_options_group', 'psm_sync_active', 'absint');
    register_setting('psm_options_group', 'psm_create_missing', 'absint');

    add_settings_section('psm_section', __('Credenciais do Site Remoto', 'product-sync-wc'), null, 'product_sync_wc');

    add_settings_field('psm_username', __('Nome de Usuário', 'product-sync-wc'), 'username_render', 'product_sync_wc', 'psm_section');
    add_settings_field('psm_password', __('Chave da API', 'product-sync-wc'), 'password_render', 'product_sync_wc', 'psm_section');
    add_settings_field('psm_url', __('URL do Site Remoto', 'product-sync-wc'), 'url_render', 'product_sync_wc', 'psm_section');

    // seção de sincronização (renderizamos manualmente no settings_page para preservar o layout antigo)
    add_settings_section('psm_sync_section', __('Sync Settings', 'product-sync-wc'), null, 'product_sync_wc');
}
