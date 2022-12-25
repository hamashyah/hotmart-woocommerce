<?php
/*
 * Plugin Name: Hotmart WooCommerce
 * Plugin URI: 
 * Description: um plugin que autentica com a API do Hotmart usando uma chave de API fornecida e, em seguida, sincroniza os produtos do Hotmart com uma loja WooCommerce.
 * Version: 1.0
 * Author: Fernando Voltolini
 * Author URI: 
 * License: GPL2
 */

// Realiza a autenticação com a API Hotmart usando a chave de acesso fornecida
function hotmart_authenticate($api_key) {
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.hotmart.com/auth",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "{\n    \"apiKey\": \"$api_key\"\n}",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json"
    ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        // Mostra um erro se houver algum problema durante a autenticação
        echo "cURL Error #:" . $err;
    } else {
        // Salva o token de acesso retornado pela API para uso posterior
        $response_data = json_decode($response);
        $access_token = $response_data->access_token;
        update_option('hotmart_access_token', $access_token);
    }
}

// Sincroniza os produtos do Hotmart com os produtos do WooCommerce
function hotmart_sync_products() {
    $access_token = get_option('hotmart_access_token');

    // Verifica se o token de acesso ainda é válido
    if (!hotmart_check_token_expired($access_token)) {
        // Se o token ainda é válido, realiza a sincronização dos produtos
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURL => "https://api.hotmart.com/product",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer $access_token"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            // Mostra um erro se houver algum problema durante a sincronização dos produtos
            echo "cURL Error #:" . $err;
        } else {
            // Salva os dados dos produtos retornados pela API em uma variável
            $products = json_decode($response);

            // Percorre cada produto retornado pela API
            foreach ($products as $product) {
                // Verifica se o produto já existe no WooCommerce
                $existing_product = wc_get_product_id_by_sku($product->sku);
                if ($existing_product) {
                    // Se o produto já existe, atualiza os dados do produto no WooCommerce
                    $wc_product = wc_get_product($existing_product);
                    $wc_product->set_name($product->name);
                    $wc_product->set_description($product->description);
                    $wc_product->set_regular_price($product->price);
                    $wc_product->set_manage_stock(true);
                    $wc_product->set_stock_quantity($product->stock);
                    $wc_product->save();
                } else {
                    // Se o produto não existe, cria um novo produto no WooCommerce
                    $new_product = array(
                        'post_title' => $product->name,
                        'post_content' => $product->description,
                        'post_status' => 'publish',
                        'post_type' => 'product',
                        'regular_price' => $product->price,
                        'manage_stock' => true,
                        'stock_quantity' => $product->stock,
                        'sku' => $product->sku
                    );
                    wc_create_product($new_product);
                }
            }
        }
    } else {
        // Se o token já expirou, realiza a autenticação novamente para obter um novo token
        $api_key = get_option('hotmart_api_key');
        hotmart_authenticate($api_key);
    }
}

// Verifica se o token de acesso ainda é válido
function hotmart_check_token_expired($access_token) {
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.hotmart.com/auth/check",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "{\n    \"access_token\": \"$access_token\"\n}",
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        // Mostra um erro se houver algum problema durante a verificação do token
        echo "cURL Error #:" . $err;
    } else {
        // Salva os dados do token retornados pela API em uma variável
        $response_data = json_decode($response);

        // Verifica se o token ainda é válido
        if ($response_data->valid) {
            return false;
        } else {
            return true;
        }
    }
}

// Cria uma seção no menu de administração do WordPress para configurar o plugin
function hotmart_admin_menu() {
    add_menu_page(
        'Hotmart',
        'Hotmart',
        'manage_options',
        'hotmart',
        'hotmart_admin_settings',
        '',
        6
    );
}
add_action('admin_menu', 'hotmart_admin_menu');

// Exibe o formuláriode configuração do plugin na seção do menu de administração do WordPress
function hotmart_admin_settings() {
    if (isset($_POST['submit'])) {
        // Salva a chave de acesso fornecida pelo usuário
        update_option('hotmart_api_key', $_POST['api_key']);

        // Realiza a autenticação com a API Hotmart usando a chave de acesso fornecida
        hotmart_authenticate($_POST['api_key']);
    }
    ?>
    <div class="wrap">
        <h1>Configurações do Plugin Hotmart</h1>
        <form method="post" action="">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="api_key">Chave de Acesso da API Hotmart</label>
                        </th>
                        <td>
                            <input name="api_key" type="text" id="api_key" value="<?php echo get_option('hotmart_api_key'); ?>" class="regular-text">
                            <p class="description" id="tagline-description">Insira sua chave de acesso da API Hotmart para sincronizar os produtos com o WooCommerce.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Salvar alterações"></p>
        </form>
    </div>
    <?php
}

// Agendado a sincronização dos produtos do Hotmart com os produtos do WooCommerce
if (!wp_next_scheduled('hotmart_sync_products_hook')) {
    wp_schedule_event(time(), 'hourly', 'hotmart_sync_products_hook');
}
add_action('hotmart_sync_products_hook', 'hotmart_sync_products');



