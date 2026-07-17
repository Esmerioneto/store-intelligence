# Egsn_StoreIntelligence

Módulo de diagnóstico e otimização de lojas Magento 2 alimentado por IA. Coleta métricas de performance, erros e vendas através de 30 collectors e envia os dados para um provedor de IA (Claude, OpenAI ou Gemini) para gerar análises, recomendações prioritizadas e estimativas de impacto.

## Requisitos

- Magento 2.4.7+
- PHP ~8.2 || ~8.3 || ~8.4

## Instalação

Via Composer:

```bash
composer require egsn/store-intelligence
bin/magento module:enable Egsn_StoreIntelligence
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

## Configuração

Lojas > Configurações > Egsn > Store Intelligence

| Seção | Campo | Descrição |
|---|---|---|
| Geral | Provedor de IA | Claude, OpenAI ou Gemini |
| Geral | API Key Gemini | Chave do Google AI Studio |
| Geral | API Key Claude | Chave da API Anthropic |
| Geral | API Key OpenAI | Chave da API OpenAI |
| Geral | Escopo da Análise | Global ou restrito a um website (collectors de vendas/pedidos) |
| Geral | Orçamento mensal de tokens | Limite de tokens de IA por mês; ao atingir, a análise continua sem resumo de IA (0 = sem limite) |
| Agendamento | Habilitado | Ativa análise automática via cron |
| Agendamento | Frequência | Diária, semanal ou mensal |
| Agendamento | Janela de execução | Horário de início/fim (ex: 01:00–05:00) |
| Limites | Dias sem venda | Threshold para produtos sem venda (padrão: 90 dias) |
| Limites | Meses inativo | Threshold para clientes inativos (padrão: 12 meses) |
| Email | Habilitado | Envio de relatório por email após análise |
| Email | Destinatários | Lista de emails separados por linha |
| Webhook | Habilitado | POST JSON `{"text": "..."}` ao concluir cada análise (compatível com Slack/Teams) |
| Webhook | URL | Endpoint do incoming webhook |

> **Consumers via cron:** configure o `cron_consumers_runner` no `app/etc/env.php`
> (`cron_run: true` + `consumers_wait_for_messages: 0`) para o Magento reciclar o
> consumer `egsn.store.intelligence.consumer` automaticamente — evita daemons com
> código antigo em memória após deploys.

## Funcionalidades

- **30 collectors** organizados em 3 categorias: Performance (7), Erros (8) e Vendas (15)
- **Análise por IA**: envia dados coletados para Claude (Anthropic), OpenAI ou Gemini (Google AI Studio) e processa a resposta em recomendações acionáveis
- **Painel admin**: dashboard com score geral, histórico de análises e lista de recomendações priorizadas (crítico / aviso / melhoria)
- **GraphQL API**: endpoints para consultar análises, recomendações e dispensar itens
- **REST API**: endpoints `GET /V1/egsn-si/analysis` e `POST /V1/egsn-si/analysis/run`
- **Cron automático**: janela de execução configurável, com suporte a horários que cruzam a meia-noite
- **Notificações por email**: relatório HTML com score, resumo da IA e recomendações críticas
- **Exportação CSV**: exportação de análise completa com todos os resultados dos collectors
- **Message Queue**: execução assíncrona via RabbitMQ/MySQL queue

## Collectors disponíveis

### Performance
- OpcacheCollector — status do OPcache
- CacheHitRatioCollector — taxa de acerto do cache Magento
- SlowQueriesCollector — queries MySQL lentas
- UnoptimizedImagesCollector — imagens acima do threshold configurado
- JsCssMinificationCollector — minificação de JS/CSS habilitada
- PageLoadTimeCollector — tempo de carregamento estimado
- ModulePerformanceCollector — módulos com impacto em performance

### Erros
- ExceptionLogCollector — exceções no exception.log
- SystemLogCollector — erros no system.log
- Missing404ImagesCollector — imagens referenciadas mas ausentes
- BrokenLinksCollector — links quebrados detectados
- FailedPaymentsCollector — pagamentos com falha recentes
- CronFailuresCollector — falhas no cron Magento
- EmailQueueCollector — emails com falha na fila
- IntegrationErrorsCollector — erros em logs de integrações (ERP, API, etc.)

### Vendas
- AbandonedCartsCollector — carrinhos abandonados
- ZeroResultSearchesCollector — buscas sem resultado
- CheckoutFunnelCollector — abandono no funil de checkout
- ProductsMissingImageCollector — produtos sem imagem
- ProductsMissingDescriptionCollector — produtos sem descrição
- EmptyCategoriesCollector — categorias sem produtos
- ExpiredCouponsCollector — cupons vencidos ativos
- PriceBelowCostCollector — produtos com preço abaixo do custo
- OutOfStockCollector — produtos esgotados
- NoSalesCollector — produtos sem vendas no período configurado
- CrossSellOpportunitiesCollector — oportunidades de cross-sell
- InactiveCustomersCollector — clientes inativos no período configurado
- LeastViewedProductsCollector — produtos menos visualizados
- TopSellingProductsCollector — produtos mais vendidos
- NegativeReviewsCollector — avaliações negativas recentes

## Changelog

Todas as mudanças relevantes deste módulo são documentadas neste arquivo.
O formato segue boas práticas inspiradas em Keep a Changelog e Semantic Versioning.

### [1.1.0] - 2026-07-16
#### Fixed
- **11 collectors que falhavam silenciosamente** em instalações com catalog staging (Adobe Commerce): joins EAV agora usam o `linkField` do `MetadataPool` (`row_id`/`entity_id`), compatível com CE e EE. Afetados: no_sales, least_viewed, cross_sell_opportunities, negative_reviews, missing_images_404, missing_image, missing_description, empty_categories, price_below_cost, out_of_stock (nome), expired_coupons (coluna `uses_total` inexistente → `salesrule_coupon.times_used`).
- `least_viewed`: coluna `object_id` inexistente em `report_viewed_product_aggregated_daily` → `product_id`.
- `page_load_time` não valida mais TLS ao medir a própria loja (certificado self-signed de dev impedia a medição).
- Notas de erro dos collectors agora incluem a mensagem real da exceção (antes só "tables not accessible").
#### Added
- Comando `bin/magento egsn:si:selfcheck`: roda os 30 collectors contra o banco real e retorna exit 1 se algum falhar — pega SQL quebrado que testes unitários (com banco mockado) não detectam.
- Webhook também notifica análises com falha (consumer e watchdog), incluindo o motivo.
- Coluna "Escopo" no Histórico (Global ou nome do website) e ID linkando para a nova página de detalhe da análise (status, score, resumo, resultados dos 30 collectors com observações, exportação CSV).
- Consumo de tokens de IA do mês (usado × orçamento) exibido no card Ações do dashboard.
- Data patch que dispensa duplicatas legadas de recomendações (versões antigas ativas do mesmo collector).
- `CollectorPool` compartilhado entre Orchestrator e self-check (lista única de collectors no di.xml).
- Watchdog no cron de limpeza: análises presas em "Em execução" além do timeout global são marcadas como falhas (com motivo).
- Coluna `error_message` na análise; o Histórico mostra o motivo da falha como tooltip no badge de status.
- Fallback de provider de IA: se o provider configurado falhar (rate limit, chave inválida), os demais providers configurados são tentados antes de marcar falha.
- Dashboard: cards de score por categoria (Performance / Erros / Vendas).
- Dashboard: card "O que mudou desde a última análise" comparando as duas últimas análises concluídas (problemas novos, resolvidos e persistentes).
- Botão "Analisar Agora" com acompanhamento do status (polling) e atualização automática da página ao concluir.
- Deduplicação de recomendações: ao concluir uma análise, versões antigas da mesma recomendação (mesmo collector + título) são dispensadas automaticamente.
- Notificação via webhook genérico (`{"text": "..."}`, compatível com Slack e Microsoft Teams).
- Orçamento mensal de tokens de IA com bloqueio da chamada de IA ao atingir o limite (a análise continua com score determinístico).
- Escopo de análise por website: collectors de vendas/pedidos filtram por `store_id`/`website_id` do website configurado; coluna `website_id` na análise.
- Data interfaces tipadas (`AnalysisInterface`, `RecommendationInterface`) fechando o contrato REST/GraphQL de `getById`/`getLatest`.
- i18n: strings do admin extraídas para `__()`/`$t()` com `i18n/pt_BR.csv` e `i18n/en_US.csv`.
- Testes para PromptBuilder, EmailNotifier, WebhookNotifier, CsvExporter, GarbageCollection (watchdog), ProviderPool (fallback) e collectors FailedPayments/AbandonedCarts.
- Coluna "Análise" na grid de recomendações, identificando de qual análise cada recomendação veio.
- Ícone SVG próprio (hexágono com circuito, derivado do logo EGSN) no grupo "Egsn" do menu lateral do admin, aplicado via CSS mask com `currentColor`.
- Link do título da recomendação na listagem para a tela de detalhe.
#### Changed
- ⚠️ Enums do GraphQL (`AnalysisStatus`, `AnalysisCategory`, `RecommendationPriority`) agora em SCREAMING_SNAKE_CASE (`COMPLETED`, `PERFORMANCE`, `CRITICAL`…), conforme o padrão Magento — breaking para clientes GraphQL que usavam valores minúsculos.
- ⚠️ `AnalysisRepositoryInterface::getById`/`getLatest` retornam `AnalysisInterface` tipada (antes array); `getLatest` lança `NoSuchEntityException` quando não há análise concluída (antes retornava vazio).
- Conformidade total com o Magento Coding Standard: 0 erros e 0 warnings no PHPCS (docblocks completos, linhas ≤120, `Filesystem`/`Io\File` no lugar de `is_dir`/`basename`; raw SQL dos collectors mantido com `phpcs:disable` justificado).
- ⚠️ Score geral agora é calculado deterministicamente (média dos scores dos collectors ponderada pela severidade do status) em vez de gerado pela IA — análises com os mesmos dados produzem o mesmo score. A escala muda em relação às análises antigas, cujo score era um julgamento subjetivo do modelo.
- Providers de IA (Claude, OpenAI, Gemini) passam a usar `temperature: 0`, e o prompt instrui a IA a citar somente problemas presentes nos dados — reduz variação e alucinação em resumos/recomendações.
- Documentação atualizada para incluir o provedor Gemini (Google AI Studio).
- `db_schema.xml` com `identity="false"` explícito em colunas int/smallint não auto-incremento.
#### Fixed
- Conformidade com o Magento Coding Standard (0 erros no PHPCS): escapes explícitos nos templates, `$escaper` no lugar dos métodos de escape do block, interfaces `HttpGet/HttpPostActionInterface` nos controllers, `AnalysisRepository` migrado para select builder com log de falhas, `EmailNotifier` com `Escaper`, `CsvExporter` com `Filesystem` do framework, remoção de código de debug e dos ui_components/grid collections sem uso, `setup_version` removido do `module.xml` e `dismissRecommendation` retornando o resultado real do update.
- Ícone fantasma antes de "Dashboard" no menu admin: o id `Egsn_StoreIntelligence::dashboard` gerava a classe CSS `item-dashboard`, que colide com o ícone do Dashboard nativo do tema admin; id do item de menu renomeado para `Egsn_StoreIntelligence::si_dashboard` (recurso de ACL inalterado).
- Labels de data no gráfico de evolução do score do dashboard (coluna `created_at` ausente no `getList`).
- Plural incorreto "recomendaçãoões" na listagem de recomendações.
- Teste unitário do `ClaudeProvider` atualizado para o construtor com `EncryptorInterface`.

### [1.0.0] - 2026-06-25 - EGSN-0000
#### Added
- Versão inicial do módulo com 30 collectors, integração com Claude e OpenAI, painel administrativo, API REST, GraphQL, cron automático e notificações por email.

## Licença

[OSL-3.0](https://opensource.org/licenses/OSL-3.0) / [AFL-3.0](https://opensource.org/licenses/AFL-3.0)
