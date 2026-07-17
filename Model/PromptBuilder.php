<?php
/**
 * Esmerio Neto
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future.
 *
 * @category Egsn
 * @package Egsn_StoreIntelligence
 *
 * @copyright Copyright (c) 2026 Esmerio Neto.
 *
 * @author Esmerio Neto <esmerioneto@gmail.com>
 */
declare(strict_types=1);

namespace Egsn\StoreIntelligence\Model;

use Egsn\StoreIntelligence\Collector\CollectionResult;

class PromptBuilder
{
    private const ITEMS_PER_COLLECTOR = 10;

    /**
     * Build system prompt.
     *
     * @return string
     */
    public function buildSystemPrompt(): string
    {
        return <<<PROMPT
Você é um especialista em e-commerce Magento 2. Analise os dados fornecidos e retorne
EXCLUSIVAMENTE um JSON válido (sem markdown, sem texto extra) com o seguinte formato:
{
  "score": <repita exatamente o "Score de saúde calculado" informado nos dados>,
  "summary": "<diagnóstico em 2-3 frases em português brasileiro, direto e prático>",
  "recommendations": [
    {
      "collector": "<código do collector>",
      "category": "<performance|errors|sales>",
      "priority": "<critical|warning|improvement>",
      "title": "<título curto>",
      "description": "<descrição do problema citando especificamente os itens afetados: SKUs, nomes de produtos,
IDs de pedidos, métodos de pagamento, emails de clientes, nomes de arquivos etc quando disponíveis>",
      "action": "<ação recomendada passo a passo>",
      "estimated_impact": "<impacto estimado em R$ ou % quando possível>"
    }
  ]
}
Ordene as recomendações por prioridade (critical primeiro). Seja direto e prático.
Nas descrições, sempre cite os itens específicos listados nos dados (SKUs, pedidos, clientes, arquivos).
O summary e as recommendations devem se basear EXCLUSIVAMENTE nos problemas presentes nos dados
(status WARNING ou CRITICAL); não mencione problemas que os dados não mostram.
PROMPT;
    }

    /**
     * Build user prompt.
     *
     * @param array $results
     * @param array $storeContext
     * @return string
     */
    public function buildUserPrompt(array $results, array $storeContext): string
    {
        $lines = ["## Dados da Loja"];
        foreach ($storeContext as $key => $value) {
            $lines[] = "- {$key}: {$value}";
        }

        $byCategory = ['performance' => [], 'errors' => [], 'sales' => []];
        foreach ($results as $result) {
            $byCategory[$result->getCategory()][] = $result;
        }

        foreach ($byCategory as $category => $categoryResults) {
            if (empty($categoryResults)) {
                continue;
            }
            $lines[] = "\n## " . ucfirst($category);
            foreach ($categoryResults as $result) {
                $summary = json_encode($result->getSummary(), JSON_UNESCAPED_UNICODE);
                $status  = strtoupper($result->getStatus());
                $lines[] = "- [{$status}] {$result->getCollectorCode()}: {$summary}";

                $items = $result->getItems();
                if (!empty($items) && $result->getStatus() !== 'ok') {
                    $sample = array_slice($items, 0, self::ITEMS_PER_COLLECTOR);
                    $lines[] = "  itens: " . json_encode($sample, JSON_UNESCAPED_UNICODE);
                    if (count($items) > self::ITEMS_PER_COLLECTOR) {
                        $lines[] = "  (+" . (count($items) - self::ITEMS_PER_COLLECTOR) . " outros)";
                    }
                }
            }
        }

        return implode("\n", $lines);
    }
}
