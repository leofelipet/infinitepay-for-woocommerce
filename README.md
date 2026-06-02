# InfinitePay para WooCommerce

Gateway de pagamento para [WooCommerce](https://woocommerce.com/) usando o [Checkout Integrado InfinitePay](https://www.infinitepay.io/checkout-documentacao) — PIX e cartão com redirect, webhook e confirmação via API.

## Requisitos

| Requisito | Versão mínima |
|-----------|----------------|
| WordPress | 6.0 |
| PHP | 7.4 |
| WooCommerce | 7.0 |

Testado com WooCommerce até **9.0**. Compatível com **checkout em blocos** e **HPOS**.

## Instalação

1. Clone ou copie este repositório para `wp-content/plugins/infinitepay`.
2. Ative **InfinitePay para WooCommerce** em *Plugins*.
3. Em *WooCommerce → Ajustes → Pagamentos → InfinitePay*:
   - Ative o gateway
   - Informe seu **Handle** (InfiniteTag, sem `$`)
   - Anote a **URL do webhook** para uso na InfinitePay

## Fluxo de pagamento

1. Cliente finaliza o pedido no WooCommerce (`pending`).
2. Plugin cria link via `POST https://api.checkout.infinitepay.io/links`.
3. Cliente paga na InfinitePay (PIX ou cartão).
4. Webhook + `payment_check` confirmam o pagamento → pedido `processing` / `completed`.

## Configuração

| Campo | Descrição |
|-------|-----------|
| Handle | Sua InfiniteTag no app InfinitePay |
| URL do webhook | Endpoint fixo da loja (`wc-api=infinitepay`) |
| URL de redirect | Thank-you page por pedido |
| Debug | Logs em *WooCommerce → Status → Logs* (source: `infinitepay`) |

## Estrutura

```
infinitepay/
├── infinitepay.php
├── includes/
│   ├── class-infinitepay-gateway.php
│   ├── class-infinitepay-api.php
│   ├── class-infinitepay-order.php
│   ├── class-infinitepay-webhook.php
│   └── class-infinitepay-blocks-support.php
└── assets/js/infinitepay-blocks.js
```

## Documentação

- [InfinitePay — Checkout Integrado](https://www.infinitepay.io/checkout-documentacao)
- [AGENTS.md](./AGENTS.md) — guia para desenvolvimento e agentes de IA

## Licença

GPL-2.0-or-later — veja [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

## Autor

**Léo Felipe** — [WhatsApp](https://wa.me/5514981453663)
