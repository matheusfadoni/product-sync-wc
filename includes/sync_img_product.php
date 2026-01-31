<?php

$image_name_before_removal = '';

function psm_get_remote_credentials() {
    return [
        'username' => get_option('psm_username'),
        'password' => get_option('psm_password'),
        'url' => get_option('psm_url'),
    ];
}

function psm_get_local_image_stats($attachment_id) {
    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !file_exists($file_path)) return null;

    $size = @filesize($file_path);
    $mtime = @filemtime($file_path);

    if ($size === false || $mtime === false) return null;

    return [
        'attachment_id' => (int) $attachment_id,
        'size' => (int) $size,
        'mtime' => (int) $mtime,
    ];
}

function psm_head_remote_image_stats($remote_image_url) {
    if (empty($remote_image_url) || !filter_var($remote_image_url, FILTER_VALIDATE_URL)) return null;

    $response = wp_remote_head($remote_image_url, ['timeout' => 10]);
    if (is_wp_error($response)) return null;

    $content_length = wp_remote_retrieve_header($response, 'content-length');
    $last_modified = wp_remote_retrieve_header($response, 'last-modified');

    $content_length = is_array($content_length) ? end($content_length) : $content_length;
    $last_modified = is_array($last_modified) ? end($last_modified) : $last_modified;

    return [
        'content_length' => is_numeric($content_length) ? (int) $content_length : null,
        'last_modified' => is_string($last_modified) && $last_modified !== '' ? $last_modified : null,
    ];
}

function check_and_create_sync_table() { // Checar e criar tabela de sincronização de imagens
    global $wpdb;
    $table_name = $wpdb->prefix . 'psm_product_image_sync';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            sku VARCHAR(255) NOT NULL,
            image_url TEXT NOT NULL,
            status ENUM('pending', 'applied', 'error') NOT NULL DEFAULT 'pending',
            error_message TEXT DEFAULT NULL,
            retry_count INT UNSIGNED DEFAULT 0,
            last_attempt DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

function save_product_image_sync_record($product_id, $sku, $image_url, $status = 'pending', $error_message = NULL) { // Salvar registro de sincronização de imagem
    global $wpdb;
    check_and_create_sync_table();
    
    $table_name = $wpdb->prefix . 'psm_product_image_sync';
    $wpdb->insert(
        $table_name,
        [
            'product_id' => $product_id,
            'sku' => $sku,
            'image_url' => $image_url,
            'status' => $status,
            'error_message' => $error_message,
            'retry_count' => 0,
            'last_attempt' => current_time('mysql')
        ],
        ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
    );
}

function update_sync_status($product_id, $status, $error_message = NULL) { // Atualizar status de sincronização
    global $wpdb;
    $table_name = $wpdb->prefix . 'psm_product_image_sync';

    // Buscar o retry_count atual
    $retry_count = $wpdb->get_var($wpdb->prepare("SELECT retry_count FROM $table_name WHERE product_id = %d", $product_id));
    $retry_count = is_null($retry_count) ? 0 : $retry_count + 1;

    $wpdb->update(
        $table_name,
        [
            'status' => $status,
            'error_message' => $error_message,
            'retry_count' => $retry_count,
            'last_attempt' => current_time('mysql', 1)
        ],
        ['product_id' => $product_id],
        ['%s', '%s', '%d', '%s'],
        ['%d']
    );
}






// __________________________________________ OLD FUNCTIONS __________________________________________
// Functions to sync images of products
function sync_on_product_save($post_id, $post, $update) {
    if ($post->post_type !== 'product' || !$update) return;

    $sku = get_post_meta($post_id, '_sku', true);
    if (!$sku) {
        log_img_product("[LOCAL] [ERRO] Produto sem SKU no post ID $post_id. Encerrando execução.");
        return;
    }

    $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
    if (!$thumbnail_id && !empty($GLOBALS['image_name_before_removal'])) {
        log_img_product("[REMOTO] Produto com SKU $sku teve imagem removida."); 
        check_and_remove_image_on_update($post_id, $post, $update);
        return;
    }

    if (!$thumbnail_id) {
        log_img_product("[LOCAL] Produto com SKU $sku não possui imagem destacada. Encerrando execução.");
        return;
    }

    // Se nada mudou localmente e a imagem remota parece igual, evita chamadas desnecessarias.
    $local_stats = psm_get_local_image_stats($thumbnail_id);
    $last_synced_attachment_id = (int) get_post_meta($post_id, '_psm_last_synced_attachment_id', true);
    $last_synced_size = (int) get_post_meta($post_id, '_psm_last_synced_filesize', true);
    $last_synced_mtime = (int) get_post_meta($post_id, '_psm_last_synced_filemtime', true);
    $remote_image_src = get_post_meta($post_id, '_psm_remote_image_src', true);

    if ($local_stats
        && $last_synced_attachment_id === (int) $thumbnail_id
        && $last_synced_size === (int) $local_stats['size']
        && $last_synced_mtime === (int) $local_stats['mtime']
        && !empty($remote_image_src)
    ) {
        $remote_stats = psm_head_remote_image_stats($remote_image_src);
        $remote_last_length = get_post_meta($post_id, '_psm_remote_image_content_length', true);
        $remote_last_modified = get_post_meta($post_id, '_psm_remote_image_last_modified', true);

        if ($remote_stats
            && $remote_stats['content_length'] !== null
            && (string) $remote_last_length !== ''
            && (int) $remote_last_length === (int) $remote_stats['content_length']
            && !empty($remote_last_modified)
            && (string) $remote_last_modified === (string) $remote_stats['last_modified']
        ) {
            log_img_product("[LOCAL] SKU $sku: imagem local inalterada e remota equivalente (content-length/last-modified). Pulando sync.");
            return;
        }
    }

    $image_url = wp_get_attachment_url($thumbnail_id);
    if (!$image_url) {
        log_img_product("[ERRO] Não foi possível obter a URL da imagem para o SKU $sku.");
        return;
    }

    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
        log_img_product("[ERRO] URL da imagem inválida para SKU $sku: $image_url");
        return;
    }

    try {
        sync_update_product_photo($post_id, $sku, $image_url, $thumbnail_id);
    } catch (Exception $e) {
        log_img_product("[ERRO] Erro inesperado ao sincronizar imagem para SKU $sku: " . $e->getMessage());
    }
}

// Função de sincronização de imagem (envia ao outro site)
function sync_update_product_photo($post_id, $sku, $image_url, $thumbnail_id) {
    $creds = psm_get_remote_credentials();
    $username = $creds['username'];
    $password = $creds['password'];
    $url = $creds['url'];

    log_img_product("[REQUISIÇÃO] Sincronização iniciada para SKU: $sku. URL da imagem: $image_url");

    try {
        // Monta a URL da requisição
        // Validação e montagem da URL final
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            log_img_product("[ERRO] URL base inválida ou vazia: " . ($url ?: 'NULO'));
            return;
        }
        $url = trim($url); // Remove espaços extras
        $request_url = rtrim($url, '/') . '/wp-json/wc/v3/products?sku=' . $sku;
        log_img_product("[REQUISIÇÃO] URL final concatenada: $request_url");


        $response = wp_remote_get($request_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            )
        ));

        if (is_wp_error($response)) {
            log_img_product("[ERRO] Erro na conexão com SKU $sku: " . $response->get_error_message());
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            log_img_product("[ERRO] Código de resposta da API: $response_code. Resposta: $response_body. URL usada: $request_url");
            return;
        }

        $body = json_decode($response_body, true);

        if (empty($body) || !isset($body[0]['id'])) {
            log_img_product("[ERRO] Produto SKU $sku não encontrado no outro site. Resposta: $response_body");
            return;
        }

        $product_id = $body[0]['id'];
        log_img_product("[REQUISIÇÃO] Produto encontrado. ID: $product_id. Tentando adicionar imagem...");

        $update_response = wp_remote_post(rtrim($url, '/') . "/wp-json/wc/v3/products/$product_id", array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'images' => array(
                    array('src' => $image_url)
                )
            ))
        ));

        // Log do corpo da requisição
        log_img_product("[REQUISIÇÃO] Corpo da requisição para SKU $sku: " . json_encode(array(
            'images' => array(
                array('src' => $image_url)
            )
        )));

        if (is_wp_error($update_response)) {
            log_img_product("[ERRO] Erro ao adicionar imagem para SKU $sku: " . $update_response->get_error_message());
            return;
        }

        $update_response_code = wp_remote_retrieve_response_code($update_response);
        $update_response_body = wp_remote_retrieve_body($update_response);

        if ($update_response_code !== 200) {
            log_img_product("[ERRO] Código de resposta do PUT: $update_response_code. Resposta: $update_response_body");
            return;
        }

        $updated_body = json_decode($update_response_body, true);
        if (is_array($updated_body) && !empty($updated_body['images'][0])) {
            $remote_image_id = isset($updated_body['images'][0]['id']) ? (int) $updated_body['images'][0]['id'] : 0;
            $remote_image_src = isset($updated_body['images'][0]['src']) ? (string) $updated_body['images'][0]['src'] : '';

            if ($remote_image_id > 0) {
                update_post_meta($post_id, '_psm_remote_image_id', $remote_image_id);
            }
            if ($remote_image_src !== '') {
                update_post_meta($post_id, '_psm_remote_image_src', $remote_image_src);

                $remote_stats = psm_head_remote_image_stats($remote_image_src);
                if ($remote_stats) {
                    if ($remote_stats['content_length'] !== null) {
                        update_post_meta($post_id, '_psm_remote_image_content_length', (int) $remote_stats['content_length']);
                    }
                    if (!empty($remote_stats['last_modified'])) {
                        update_post_meta($post_id, '_psm_remote_image_last_modified', (string) $remote_stats['last_modified']);
                    }
                }
            }
        }

        $local_stats = psm_get_local_image_stats($thumbnail_id);
        if ($local_stats) {
            update_post_meta($post_id, '_psm_last_synced_attachment_id', (int) $thumbnail_id);
            update_post_meta($post_id, '_psm_last_synced_filesize', (int) $local_stats['size']);
            update_post_meta($post_id, '_psm_last_synced_filemtime', (int) $local_stats['mtime']);
        }

        log_img_product("[REQUISIÇÃO] Imagem adicionada com sucesso ao SKU $sku no outro site.");
    } catch (Exception $e) {
        log_img_product("[ERRO] Erro inesperado ao sincronizar imagem para SKU $sku: " . $e->getMessage());
    }
}

function check_and_remove_image_on_update($post_id, $post, $update) {
    if (!$update) return;

    try {
        $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
        if (!$thumbnail_id) {
            global $image_name_before_removal;
            $sku = get_post_meta($post_id, '_sku', true);

            $remote_image_id = (int) get_post_meta($post_id, '_psm_remote_image_id', true);
            $remote_image_src = (string) get_post_meta($post_id, '_psm_remote_image_src', true);

            // Preferir deletar pelo ID remoto salvo (mais preciso que "por nome").
            if ($remote_image_id > 0) {
                $deleted = sync_remove_remote_media_by_id($remote_image_id);
                if ($deleted) {
                    log_img_product("[LOCAL] [REQUISIÇÃO] SKU $sku: imagem removida no remoto via ID $remote_image_id.");
                    delete_post_meta($post_id, '_psm_remote_image_id');
                    delete_post_meta($post_id, '_psm_remote_image_src');
                    delete_post_meta($post_id, '_psm_remote_image_content_length');
                    delete_post_meta($post_id, '_psm_remote_image_last_modified');
                } else {
                    log_img_product("[ERRO] SKU $sku: falha ao remover imagem remota via ID $remote_image_id. Tentando fallback por nome.");
                }
            }

            if ($remote_image_id <= 0) {
                $fallback_name = '';
                if (!empty($remote_image_src)) {
                    $fallback_name = basename($remote_image_src);
                } elseif ($image_name_before_removal) {
                    $fallback_name = $image_name_before_removal;
                }

                if ($fallback_name) {
                    sync_remove_image_by_name($fallback_name);
                    log_img_product("[LOCAL] [REQUISIÇÃO] Imagem destacada removida para SKU: $sku. Fallback nome: $fallback_name");
                }
            }

            $image_name_before_removal = '';
        }
    } catch (Exception $e) {
        log_img_product("[ERRO] Erro inesperado ao verificar/remover imagem para SKU $sku: " . $e->getMessage());
    }
}

function sync_remove_remote_media_by_id($media_id) {
    $creds = psm_get_remote_credentials();
    $username = $creds['username'];
    $password = $creds['password'];
    $url = $creds['url'];

    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        log_img_product("[ERRO] URL base invalida ao tentar remover media por ID: " . ($url ?: 'NULO'));
        return false;
    }

    $delete_url = rtrim($url, '/') . "/wp-json/wp/v2/media/" . (int) $media_id;

    $delete_response = wp_remote_request($delete_url, [
        'method' => 'DELETE',
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
        ],
        'body' => ['force' => true],
        'timeout' => 20,
    ]);

    if (is_wp_error($delete_response)) {
        log_img_product("[ERRO] Erro ao remover media ID $media_id: " . $delete_response->get_error_message());
        return false;
    }

    $code = wp_remote_retrieve_response_code($delete_response);
    if ($code !== 200) {
        $body = wp_remote_retrieve_body($delete_response);
        log_img_product("[ERRO] Remocao media ID $media_id retornou HTTP $code. Resposta: $body");
        return false;
    }

    return true;
}

function sync_remove_image_by_name($image_name) {
    $creds = psm_get_remote_credentials();
    $username = $creds['username'];
    $password = $creds['password'];
    $url = $creds['url'];

    log_img_product("[REQUISIÇÃO] Iniciando remoção de imagem com nome: $image_name");

    try {
        $media_search_url = rtrim($url, '/') . "/wp-json/wp/v2/media?search=" . urlencode($image_name);
        log_img_product("[REQUISIÇÃO] URL para busca de mídia: $media_search_url");

        $response = wp_remote_get($media_search_url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            )
        ));

        if (is_wp_error($response)) {
            log_img_product("[ERRO] Erro ao conectar para buscar imagem: " . $response->get_error_message());
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body)) {
            log_img_product("[ERRO] Imagem com nome $image_name não encontrada no outro site.");
            return;
        }

        foreach ($body as $media_item) {
            if (strpos($media_item['source_url'], $image_name) !== false) {
                $image_id = $media_item['id'];
                $delete_url = rtrim($url, '/') . "/wp-json/wp/v2/media/$image_id";

                log_img_product("[REQUISIÇÃO] Tentando remover a imagem com ID $image_id no outro site.");

                $delete_response = wp_remote_request($delete_url, array(
                    'method' => 'DELETE',
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
                    ),
                    'body' => array('force' => true)
                ));

                if (is_wp_error($delete_response)) {
                    log_img_product("[ERRO] Erro ao remover imagem com ID $image_id: " . $delete_response->get_error_message());
                } else {
                    log_img_product("[REQUISIÇÃO] Imagem com nome $image_name e ID $image_id removida com sucesso do outro site.");
                }
            }
        }
    } catch (Exception $e) {
        log_img_product("[ERRO] Erro inesperado ao remover imagem: " . $e->getMessage());
    }
}

function capture_image_name_before_update($post_id, $data) {
    global $image_name_before_removal;

    if (get_post_type($post_id) !== 'product') return;

    try {
        $thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
        if ($thumbnail_id) {
            $image_url = wp_get_attachment_url($thumbnail_id);
            $image_name_before_removal = basename($image_url);
            log_img_product("[LOCAL] [CAPTURA] Imagem capturada antes da atualização: $image_name_before_removal");
        }
    } catch (Exception $e) {
        log_img_product("[ERRO] Erro inesperado ao capturar nome da imagem: " . $e->getMessage());
    }
}
