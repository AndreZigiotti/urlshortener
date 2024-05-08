<?php
/*
* Plugin Name: URL Shortener
* Plugin URI: https://example.com/plugins/the-basics/
* Description: Handle the basics with this plugin.
* Version: 1.0.0
* Requires at least: 5.2
* Requires PHP: 7.2
* Author: INGAGE Digital
* Author URI: https://ingagedigital.com.br
* License: GPL v2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* Update URI: https://example.com/my-plugin/
*/

/*
  0- Redirecionar visitante para URL de destino
  1- Script para gerar o slug com pelo menos 8 caracteres
  2- Filtrar slug do post
  3- Gerar o QR Code em png com a url encurtada
  4- Verificar período de ativação do link
  5- Capturar tags de analytics
  6- Salvar contador
  7- Salva o referrer 
  8- Caixa para exibir o QR Code gerado
  9- Caixa para exibir as estatísticas
*/

require plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use chillerlan\QRCode\QRCode;

// wp_hash($urlDestino);

add_filter('pre_wp_unique_post_slug', 'shorturl_filtrar_slug_post', 10, 6);

function shorturl_filtrar_slug_post($override_slug, $slug, $post_id, $post_status, $post_type, $post_parent)
{
  if ($post_type == 'u') {
    $urlDestino = get_field('url_destino');

    $urlPersonalizada = get_field('custom_url');

    if ($urlPersonalizada != '') {
      $override_slug = sanitize_title($urlPersonalizada);
    } else {
      $hash = substr(wp_hash($urlDestino), 0, 8);
      $exists = get_page_by_path($hash, OBJECT, 'u');
      error_log(print_r($exists, true));

      if ($exists) {
        $parts = str_split($hash, 7);
        $listaCaracteres = str_replace('0123456789abcdefghijklmnopqrstuvwxyz', $parts[1], '');
        $caracteres = substr(str_shuffle($listaCaracteres), 1, 1);
        $override_slug = $parts[0] . $caracteres;
      } else {
        $override_slug = $hash;
      }
    }
  }
  return $override_slug;
}

/**
 * Register meta box(es).
 */
function shorturl_exibe_itens_encurtador()
{
  add_meta_box('shorturl_box', 'Informações contador (QR Code)', 'shorturl_exibe_info', 'u');
}
add_action('add_meta_boxes', 'shorturl_exibe_itens_encurtador');

function shorturl_exibe_info($post)
{
  //print_r($post);
  $curl = curl_init();

  // curl_setopt_array($curl, array(
  //   CURLOPT_URL => 'https://url-shortener-wordpress-sensia.ingage.workers.dev/api/list',
  //   CURLOPT_RETURNTRANSFER => true,
  //   CURLOPT_ENCODING => '',
  //   CURLOPT_MAXREDIRS => 10,
  //   CURLOPT_TIMEOUT => 0,
  //   CURLOPT_FOLLOWLOCATION => true,
  //   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  //   CURLOPT_CUSTOMREQUEST => 'GET',
  //   CURLOPT_HEADER => true,
  //   CURLOPT_HTTPHEADER => array(
  //     'X-Auth-Email: gabriel@ingagedigital.com.br',
  //     'X-Auth-Key: e9c70beb39f152ad6dafd2ced69ae6d7d69f9',
  //     'Content-Type: application/json',
  //     'Authorization: Bearer ryhg6WZHvZFqUKCcPKZVsDpZyTmu_vEFhTDz54Ry',
  //   ),
  // ));

  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_URL, 'https://url-shortener-wordpress-sensia.ingage.workers.dev/api/list');

  $response = curl_exec($curl);
  $data = json_decode($response, true);
  //var_dump($data);
  echo '<pre>';
  print_r($data);
  echo '</pre>';
  curl_close($curl);


  $clicks_counter = get_post_meta($post->ID, 'clicks_counter', true);
  if ($clicks_counter == '') {
    $clicks_counter = 0;
  }
  echo '<p>Quantidade de clicks: ' . $clicks_counter . '</p>';

  $data = get_permalink($post->ID);

  echo '<img src="' . (new QRCode)->render($data) . '" alt="QR Code" width="400px" />';

  $referrers = get_post_meta($post->ID, 'referrer');
  $unique = [];
  //print_r($referrers);

  foreach ($referrers as $referrer) {
    if (!isset($unique[$referrer])) {
      $unique[$referrer] = 1;
    } else {
      $unique[$referrer] = $unique[$referrer] + 1;
    }
  }
  echo '<table class="wp-list-table widefat fixed striped table-view-list posts">';
  echo '<tr class="wp-list-table"><th>Referrer</th><th>Quantidade de clicks</th></tr>';
  foreach ($unique as $key => $value) {
    echo '<tr><td>' . esc_url($key) . '</td><td>' . $value . '</td></tr>';
  }
  echo '</table>';
}

add_filter('template_include', 'shorturl_redirect');

function shorturl_redirect($template)
{
  if (!is_singular('u')) {
    return $template;
  } else {
    global $post;
    $dataInicio = get_field('data_inicial');
    $dataFim = get_field('data_final');
    $today = new DateTime('now');

    //print_r($dataInicio);
    //print_r($dataFim);

    if ($dataInicio !== '') {
      $start = new DateTime($dataInicio);
      if ($start > $today) {
        die('Link inativo');
      }
    }
    if ($dataFim !== '') {
      $end = new DateTime($dataFim);
      if ($end < $today) {
        die('Link expirado');
      }
    }

    $urlDestino = get_field('url_destino');

    $clicks_counter = get_post_meta($post->ID, 'clicks_counter', true);
    $clicks_counter++;

    update_post_meta($post->ID, 'clicks_counter', $clicks_counter);

    if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
      add_post_meta($post->ID, 'referrer', sanitize_url($_SERVER['HTTP_REFERER']), false);
    }

    wp_redirect($urlDestino, 302);
    die();
  }
}


// function send_to_worker($post_id)
// {
//   $urlDestino = get_field('url_destino', $post_id);
//   $custom_url = get_field('custom_url', $post_id);
//   $activation_date = get_field('data_inicial', $post_id);
//   $expiration_date = get_field('data_final', $post_id);

//   $body = [
//     'url' => $urlDestino,
//     'activation' => $activation_date,
//     'expiration' => $expiration_date,
//     'clicks' => 0,  // inicializa a contagem de cliques
//     'referrer' => []  // inicializa os referrers
//   ];

//   $response = wp_remote_post("https://url-shortener-wordpress-sensia.ingage.workers.dev/api/shorten/", [
//     'body' => json_encode($body),
//     'headers' => ['Content-Type' => 'application/json']
//   ]);

//   if (is_wp_error($response)) {
//     error_log('Failed to send data to Cloudflare Worker: ' . $response->get_error_message());
//   }
// }

function send_to_worker($post_id)
{
  $curl = curl_init();

  $dadosJS = [
    'expirationTime' => '',
    'requirePassword' => false,
    'password' => '',
    'shortUrlLength' => 8,
    'longUrl' => 'url:' . get_field('url_destino', $post_id),
    'shortUrl' => 'url:' . get_field('custom_url', $post_id),
    //clicks: 0,
  ];

  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://url-shortener-wordpress-sensia.ingage.workers.dev/api/shorten',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_POSTFIELDS => json_encode($dadosJS),
    CURLOPT_HEADER => true,
    CURLOPT_HTTPHEADER => array(
      'X-Auth-Email: gabriel@ingagedigital.com.br',
      'X-Auth-Key: e9c70beb39f152ad6dafd2ced69ae6d7d69f9',
      'Content-Type: text/plain',
      'Authorization: Bearer ryhg6WZHvZFqUKCcPKZVsDpZyTmu_vEFhTDz54Ry',
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);
  error_log($response);
}

add_action('acf/save_post', 'send_to_worker', 20); // Utiliza uma prioridade para garantir que seja executado após os dados serem salvos pelo ACF.
