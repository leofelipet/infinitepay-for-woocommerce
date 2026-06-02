# InfinitePay para WooCommerce — Guia para agentes

Este documento é o contrato de implementação do plugin. Siga estas decisões; não reintroduza escopo fora do MVP sem alinhamento explícito.

## Propósito

Gateway de pagamento WooCommerce que integra o **Checkout Integrado** da InfinitePay: ao finalizar o pedido, o cliente é redirecionado para o checkout hospedado da InfinitePay (PIX/cartão). A confirmação do pagamento atualiza o pedido no WooCommerce.

## Decisões do MVP (imutáveis)

| Tópico | Decisão |
|--------|---------|
| Fluxo | Redirect para checkout hospedado InfinitePay |
| Confirmação | Webhook primário + redirect na thank-you como fallback/UX |
| Credencial | Um `handle` (InfiniteTag, sem `$`) nas configurações do gateway |
| `order_nsu` | ID numérico do pedido WooCommerce (`$order->get_id()`) |
| Itens | Linhas detalhadas (produtos, frete, taxas, desconto); valores em centavos |
| Compatibilidade | Checkout Blocks + clássico + HPOS |
| Prefill | `customer` + `address` quando disponíveis no pedido |
| Segurança | Sempre `payment_check` na API antes de `payment_complete()` |
| Status | `pending` → `processing` ou `completed` (pedidos só virtuais/download) |
| Testes | Mesmo handle de produção com valores baixos |
| Identidade | Plugin **InfinitePay para WooCommerce**, slug `infinitepay`, gateway id `infinitepay` |
| Autor | **Léo Felipe** — https://wa.me/5514981453663 |

### Fora de escopo no MVP

- Reembolsos / estornos via API
- Assinaturas e pagamentos recorrentes
- Multi-handle / marketplace
- Campos de cartão no checkout WooCommerce (não há API para isso)
- Modo sandbox simulado no plugin

## API InfinitePay

Base oficial: `https://api.checkout.infinitepay.io`

| Endpoint | Método | Uso |
|----------|--------|-----|
| `/links` | POST | Criar link de pagamento |
| `/payment_check` | POST | Confirmar status antes de marcar pedido pago |

Documentação: https://www.infinitepay.io/checkout-documentacao

### Payload `/links`

- `handle` (string, obrigatório)
- `items` (array, obrigatório) — a doc PT também cita `itens`; o plugin envia `items` e faz fallback para `itens` se a API retornar erro de validação
- `order_nsu` (string, recomendado) — ID do pedido WC
- `redirect_url` (string) — URL de retorno (order received)
- `webhook_url` (string) — notificação server-to-server
- `customer` (object, opcional) — `name`, `email`, `phone_number` (E.164, ex. `+5511999999999`)
- `address` (object, opcional) — `cep`, `street`, `neighborhood`, `number`, `complement`

Preços em **centavos** (R$ 10,00 = `1000`).

### Payload `/payment_check`

- `handle`, `order_nsu`, `transaction_nsu`, `slug` (usar `invoice_slug` do webhook como `slug` quando aplicável)

Resposta relevante: `success`, `paid`, `amount`, `paid_amount`, `capture_method`, `installments`.

### Webhook

- Responder **HTTP 200** o mais rápido possível (&lt; 1s)
- Em erro de processamento interno, preferir log + 200 se o pagamento já foi aplicado; usar 400 só se o payload for inválido e deve ser reenviado
- Corpo JSON: `invoice_slug`, `order_nsu`, `transaction_nsu`, `paid_amount`, `capture_method`, `receipt_url`, etc.

### Redirect do checkout

Query params na `redirect_url`: `receipt_url`, `order_nsu`, `slug`, `capture_method`, `transaction_nsu`. O cliente deve clicar em **Continuar** no checkout InfinitePay.

## Arquitetura do plugin

```
infinitepay/
├── infinitepay.php
├── AGENTS.md
├── includes/
│   ├── class-infinitepay-gateway.php
│   ├── class-infinitepay-api.php
│   ├── class-infinitepay-webhook.php
│   └── class-infinitepay-order.php
└── languages/
```

## Fluxo de pagamento

1. Cliente escolhe InfinitePay e finaliza o pedido.
2. `process_payment`: pedido fica `pending`; monta payload; `POST /links`.
3. Salva meta (`_infinitepay_slug`, link, etc.) e redireciona para `url` retornada pela API.
4. Cliente paga na InfinitePay.
5. **Webhook** `/?wc-api=infinitepay` recebe POST → `payment_check` → `payment_complete` (idempotente).
6. Cliente volta à thank-you com query params → mesmo fluxo de confirmação se ainda não pago.

## Configuração admin

WooCommerce → Ajustes → Pagamentos → InfinitePay:

| Campo | Option key | Descrição |
|-------|------------|-----------|
| Ativar | `enabled` | yes/no |
| Título | `title` | Nome no checkout |
| Descrição | `description` | Texto no checkout |
| Handle | `handle` | InfiniteTag sem `$` |
| Debug | `debug` | Log via `WC_Logger` (source: `infinitepay`) |

Na tela de configurações do gateway são exibidas (somente leitura) a **URL do webhook** (`WC()->api_request_url( 'infinitepay' )`) e o **padrão da URL de redirect** (`order-received/{order_id}` no checkout).

## Mapeamento pedido ↔ API

- `order_nsu` = `(string) $order->get_id()`
- Itens: cada line item do pedido; cada shipping; cada fee; linha de desconto se `get_discount_total() > 0`
- Ajuste de centavos: se soma dos itens ≠ total do pedido, corrigir na última linha
- `redirect_url` = `$order->get_checkout_order_received_url()`
- `webhook_url` = `WC()->api_request_url( 'infinitepay' )`

## Meta do pedido

| Meta key | Conteúdo |
|----------|----------|
| `_infinitepay_slug` | slug / invoice_slug |
| `_infinitepay_transaction_nsu` | ID da transação |
| `_infinitepay_capture_method` | `pix` ou `credit_card` |
| `_infinitepay_receipt_url` | Comprovante |
| `_infinitepay_paid_amount` | Valor pago (centavos) |
| `_infinitepay_payment_confirmed` | `yes` após confirmação idempotente |

## Idempotência

Antes de `payment_complete()`, verificar `_infinitepay_payment_confirmed === 'yes'`. Sempre chamar `payment_check` e exigir `paid === true`.

## Compatibilidade WooCommerce

- Declarar compatibilidade HPOS em `before_woocommerce_init` com `FeaturesUtil::declare_compatibility( 'custom_order_tables', ... )`
- Gateway sem campos extras no checkout (redirect only)
- **WooCommerce Blocks:** registrar `InfinitePay_Blocks_Support` em `woocommerce_blocks_payment_method_type_registration` + script `assets/js/infinitepay-blocks.js` (obrigatório para aparecer no checkout em blocos)
- Requer WooCommerce ativo

## Testes manuais

1. Configurar handle no admin.
2. Produto barato; finalizar com InfinitePay.
3. Verificar pedido `pending` e redirect para InfinitePay.
4. Pagar; confirmar webhook (log debug) e pedido `processing`.
5. Repetir redirect/thank-you se webhook atrasar.
6. Verificar meta e nota do pedido.

## Armadilhas conhecidas

- `items` vs `itens` na API — implementar tentativa com fallback.
- Não marcar pago sem `payment_check`.
- Telefone: normalizar para E.164 Brasil quando possível.
- Webhook pode repetir — idempotência obrigatória.
- Cupom/desconto: linha com preço negativo em centavos; total do payload deve igualar total do pedido.

## Convenções de código

- PHP 7.4+
- Prefixo de funções: `infinitepay_`
- Classes: `InfinitePay_*` em `includes/`
- Text domain: `infinitepay`
- Sem secrets no repositório; handle apenas nas opções do gateway
- GPL-2.0-or-later

## Referências WooCommerce

- [Payment gateway plugin base](https://developer.woocommerce.com/docs/features/payments/payment-gateway-plugin-base)
- [Payment Gateway API](https://developer.woocommerce.com/docs/features/payments/payment-gateway-api)
- Endpoint legado: `add_action( 'woocommerce_api_infinitepay', ... )`
