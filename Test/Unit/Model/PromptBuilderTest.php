<?php
/**
 * Esmerio Neto
 *
 * @category Egsn
 * @package Egsn_StoreIntelligence
 *
 * @copyright Copyright (c) 2026 Esmerio Neto.
 *
 * @author Esmerio Neto <esmerioneto@gmail.com>
 */
declare(strict_types=1);

namespace Egsn\StoreIntelligence\Test\Unit\Model;

use Egsn\StoreIntelligence\Collector\CollectionResult;
use Egsn\StoreIntelligence\Model\PromptBuilder;
use PHPUnit\Framework\TestCase;

class PromptBuilderTest extends TestCase
{
    /**
     * Test system prompt defines json contract.
     *
     * @return void
     */
    public function testSystemPromptDefinesJsonContract(): void
    {
        $prompt = (new PromptBuilder())->buildSystemPrompt();

        $this->assertStringContainsString('"score"', $prompt);
        $this->assertStringContainsString('"summary"', $prompt);
        $this->assertStringContainsString('"recommendations"', $prompt);
        $this->assertStringContainsString('critical|warning|improvement', $prompt);
    }

    /**
     * Test user prompt groups by category and includes context.
     *
     * @return void
     */
    public function testUserPromptGroupsByCategoryAndIncludesContext(): void
    {
        $results = [
            new CollectionResult('opcache', 'performance', 'ok', ['enabled' => true]),
            new CollectionResult('no_sales', 'sales', 'warning', ['count' => 3], [['sku' => 'A']]),
        ];

        $prompt = (new PromptBuilder())->buildUserPrompt($results, ['Plataforma' => 'Magento 2']);

        $this->assertStringContainsString('- Plataforma: Magento 2', $prompt);
        $this->assertStringContainsString('## Performance', $prompt);
        $this->assertStringContainsString('## Sales', $prompt);
        $this->assertStringContainsString('[OK] opcache', $prompt);
        $this->assertStringContainsString('[WARNING] no_sales', $prompt);
    }

    /**
     * Test user prompt samples items only for problems.
     *
     * @return void
     */
    public function testUserPromptSamplesItemsOnlyForProblems(): void
    {
        $manyItems = array_map(static fn (int $i): array => ['sku' => "SKU-{$i}"], range(1, 12));

        $results = [
            new CollectionResult('ok_collector', 'sales', 'ok', ['count' => 0], [['sku' => 'HIDDEN']]),
            new CollectionResult('warn_collector', 'sales', 'warning', ['count' => 12], $manyItems),
        ];

        $prompt = (new PromptBuilder())->buildUserPrompt($results, []);

        // itens de collectors OK não vão para o prompt
        $this->assertStringNotContainsString('HIDDEN', $prompt);
        // amostra limitada a 10, com contador do excedente
        $this->assertStringContainsString('SKU-10', $prompt);
        $this->assertStringNotContainsString('SKU-11', $prompt);
        $this->assertStringContainsString('(+2 outros)', $prompt);
    }
}
