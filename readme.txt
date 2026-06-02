=== InfinitePay para WooCommerce ===
Contributors: leofelipe
Tags: woocommerce, payment gateway, infinitepay, pix, credit card, checkout
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gateway de pagamento WooCommerce para o Checkout Integrado InfinitePay (PIX e cartão).

== Description ==

**InfinitePay para WooCommerce** conecta sua loja ao [Checkout Integrado InfinitePay](https://www.infinitepay.io/checkout-documentacao). O cliente finaliza o pedido na sua loja e é redirecionado para pagar com PIX ou cartão no ambiente InfinitePay.

= Recursos =

* Redirect para checkout hospedado InfinitePay
* Confirmação via webhook com validação `payment_check`
* Fallback na página de pedido recebido (thank you)
* Compatível com **WooCommerce Blocks** e checkout clássico
* Compatível com **HPOS** (High-Performance Order Storage)
* Pré-preenchimento de cliente e endereço quando disponível
* URLs de webhook e redirect visíveis nas configurações
* Log de depuração via WooCommerce Logger

= Requisitos =

* WordPress 6.0+
* WooCommerce 7.0+
* Conta InfinitePay com InfiniteTag (handle)

= Configuração =

1. Instale e ative o plugin.
2. Vá em **WooCommerce → Ajustes → Pagamentos → InfinitePay**.
3. Ative o método e informe seu **Handle** (InfiniteTag, sem o símbolo `$`).
4. Copie a **URL do webhook** exibida nas configurações (necessária para confirmação automática).
5. Faça um pedido de teste com valor baixo.

= Autor =

Desenvolvido por [Léo Felipe](https://wa.me/5514981453663).

== Installation ==

1. Envie a pasta `infinitepay` para `/wp-content/plugins/` ou instale pelo painel WordPress.
2. Ative o plugin em **Plugins**.
3. Configure em **WooCommerce → Ajustes → Pagamentos → InfinitePay**.

== Frequently Asked Questions ==

= O cliente paga dentro do meu site? =

Não. O pagamento é concluído no checkout hospedado da InfinitePay (redirect). Isso segue o modelo oficial do Checkout Integrado.

= Posso usar outra InfiniteTag? =

O handle configurado define qual conta InfinitePay recebe os pagamentos. Use apenas a tag da sua empresa.

= Funciona com checkout em blocos? =

Sim. O plugin registra integração com WooCommerce Blocks.

== Changelog ==

= 1.0.0 =
* Versão inicial: gateway redirect, webhook, Blocks e HPOS.

== Upgrade Notice ==

= 1.0.0 =
Versão inicial do plugin.
