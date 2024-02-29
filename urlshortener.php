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

// wp_hash($urlDestino);

add_filter('pre_wp_unique_post_slug', 'shorturl_filtrar_slug_post', 10, 6);

function shorturl_filtrar_slug_post($override_slug, $slug, $post_id, $post_status, $post_type, $post_parent)
{
  if ($post_type == 'u') {
    $urlDestino = get_field('url_destino');
    $hash = substr(wp_hash($urlDestino), 0, 8);

    $override_slug = $hash;
  }

  return $override_slug;
}
