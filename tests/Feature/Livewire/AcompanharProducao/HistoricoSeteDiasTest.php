<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\AcompanharProducao;

use App\Livewire\Dashboard\AcompanharProducao;
use App\Models\Codi\CodiPerformance;
use App\Models\ItemProgramacao;
use App\Models\Linha;
use App\Models\Produto;
use App\Models\Programacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Testa a lógica de agregação de historico_7d dentro de AcompanharProducao.
 *
 * A computação vive em carregarLinhas() e inclui:
 * - Pivot de codi_eventos → producao_qty / producao_min / parada_min por dia
 * - disponibilidade_media = producao_min / (producao_min + parada_min) * 100
 * - ritmo = qty / min * 60
 * - tendencia: 'up' se variação >= 5%, 'down' se <= -5%, 'stable' caso contrário
 */
class HistoricoSeteDiasTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers de setup
    // -------------------------------------------------------------------------

    /**
     * Cria o conjunto mínimo de registros para que uma Linha apareça
     * na saída de carregarLinhas() com historico_7d populado.
     *
     * Retorna ['linha' => Linha, 'programacao' => Programacao, 'codigo_recurso' => string]
     */
    private function criarLinhaComProgramacaoConfirmada(string $codigoLinha = 'LN04'): array
    {
        // Linha
        $linha = Linha::factory()->create([
            'codigo' => $codigoLinha,
            'nome'   => 'Linha Teste',
            'ativo'  => true,
        ]);

        // Produto (necessário pela FK em itens_programacao.sku → produtos.sku)
        $produto = Produto::firstOrCreate(
            ['sku' => 'SKU-TEST-01'],
            [
                'descricao'    => 'Produto Teste',
                'taxa_por_hora' => 600.00,
                'ativo'        => true,
            ]
        );

        // Programacao confirmada
        $programacao = Programacao::create([
            'linha_id'              => $linha->id,
            'data_inicio_planejada' => now()->subDays(1),
            'status'                => 'confirmada',
            'eficiencia'            => 100.00,
            'origem'                => 'manual',
        ]);

        // ItemProgramacao com numero_op — obrigatório para carregarLinhas() não fazer continue
        ItemProgramacao::create([
            'programacao_id'   => $programacao->id,
            'sequencia'        => 1,
            'numero_op'        => 'OP-HIST-001',
            'sku'              => $produto->sku,
            'descricao_produto' => $produto->descricao,
            'quantidade'       => 1000,
        ]);

        // Mapeia o código da linha para o nome esperado pelo componente:
        // LN04 → 'LINHA 4'  |  LN10 → 'LINHA 10'
        $numLinha        = ltrim(str_replace('LN', '', strtoupper($codigoLinha)), '0');
        $nomeRecursoCodi = 'LINHA ' . $numLinha;
        $codigoRecurso   = 'REC-' . $numLinha;

        // CodiPerformance — liga a linha ao codigo_recurso do CODI
        CodiPerformance::create([
            'codigo_recurso' => $codigoRecurso,
            'nome_recurso'   => $nomeRecursoCodi,
            'sincronizado_em' => now(),
        ]);

        return [
            'linha'           => $linha,
            'programacao'     => $programacao,
            'codigo_recurso'  => $codigoRecurso,
        ];
    }

    /**
     * Insere um evento em codi_eventos com contador sequencial para codigo_evento único.
     */
    private static int $eventoSeq = 0;

    private function inserirEvento(
        string $codigoRecurso,
        string $tipoEvento,
        string $data,
        float $quantidade,
        int $duracaoMinutos
    ): void {
        self::$eventoSeq++;
        DB::table('codi_eventos')->insert([
            'codigo_evento'   => 'EVT-' . self::$eventoSeq . '-' . uniqid(),
            'codigo_recurso'  => $codigoRecurso,
            'tipo_evento'     => $tipoEvento,
            'quantidade'      => $quantidade,
            'inicio_evento'   => $data . ' 06:00:00',
            'fim_evento'      => $data . ' 08:00:00',
            'duracao_minutos' => $duracaoMinutos,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 1 — producao_total é a soma das quantidades dos últimos 7 dias
    // -------------------------------------------------------------------------

    public function test_historico_7d_returns_producao_total(): void
    {
        // Arrange
        $setup          = $this->criarLinhaComProgramacaoConfirmada('LN04');
        $codigoRecurso  = $setup['codigo_recurso'];

        // 3 eventos de PRODUCAO nos últimos 7 dias com quantidades conhecidas
        $this->inserirEvento($codigoRecurso, 'PRODUCAO', now()->subDays(1)->toDateString(), 200.0, 60);
        $this->inserirEvento($codigoRecurso, 'PRODUCAO', now()->subDays(2)->toDateString(), 350.0, 80);
        $this->inserirEvento($codigoRecurso, 'PRODUCAO', now()->subDays(3)->toDateString(), 150.0, 40);
        // Total esperado: 700

        // Act
        $component = Livewire::test(AcompanharProducao::class);

        // Assert
        $linhas = $component->get('linhas');
        $this->assertNotEmpty($linhas, 'Nenhuma linha retornada — verificar setup mínimo.');

        $historico = $linhas[0]['historico_7d'];
        $this->assertSame(700, $historico['producao_total'],
            "producao_total deveria ser 700 (200+350+150)."
        );
    }

    // -------------------------------------------------------------------------
    // Test 2 — disponibilidade_media é null quando não há eventos
    // -------------------------------------------------------------------------

    public function test_disponibilidade_diaria_is_null_when_no_events(): void
    {
        // Arrange — setup mínimo, zero eventos para o recurso
        $this->criarLinhaComProgramacaoConfirmada('LN04');

        // Act
        $component = Livewire::test(AcompanharProducao::class);

        // Assert
        $linhas = $component->get('linhas');
        $this->assertNotEmpty($linhas);

        $historico = $linhas[0]['historico_7d'];
        $this->assertNull(
            $historico['disponibilidade_media'],
            "Sem eventos, disponibilidade_media deve ser null."
        );
    }

    // -------------------------------------------------------------------------
    // Test 3 — disponibilidade calculada corretamente: 480 / (480+120) = 80%
    // -------------------------------------------------------------------------

    public function test_disponibilidade_calculada_corretamente(): void
    {
        // Arrange
        $setup         = $this->criarLinhaComProgramacaoConfirmada('LN04');
        $codigoRecurso = $setup['codigo_recurso'];
        $data          = now()->subDays(1)->toDateString();

        // PRODUCAO: 480 min
        $this->inserirEvento($codigoRecurso, 'PRODUCAO', $data, 100.0, 480);
        // PARADA: 120 min
        $this->inserirEvento($codigoRecurso, 'PARADA', $data, 0.0, 120);
        // Disponibilidade = 480 / (480 + 120) * 100 = 80.0

        // Act
        $component = Livewire::test(AcompanharProducao::class);

        // Assert
        $linhas = $component->get('linhas');
        $this->assertNotEmpty($linhas);

        $historico = $linhas[0]['historico_7d'];
        $this->assertNotNull($historico['disponibilidade_media'],
            "disponibilidade_media não deveria ser null quando há eventos."
        );
        $this->assertEqualsWithDelta(80.0, (float) $historico['disponibilidade_media'], 0.5,
            "disponibilidade_media deveria ser ~80.0 (480/(480+120)*100)."
        );
    }

    // -------------------------------------------------------------------------
    // Test 4 — tendencia 'up' quando ritmo acelerou >= 5%
    // -------------------------------------------------------------------------

    public function test_tendencia_up_quando_ritmo_acelerou_5pct(): void
    {
        // Arrange
        $setup         = $this->criarLinhaComProgramacaoConfirmada('LN04');
        $codigoRecurso = $setup['codigo_recurso'];

        // Semana anterior (8+ dias atrás, dentro dos 14d da janela de coleta):
        // ritmo anterior = 600 qty / 60 min * 60 = 600 cx/h
        $this->inserirEvento($codigoRecurso, 'PRODUCAO', now()->subDays(8)->toDateString(), 600.0, 60);

        // Semana atual (últimos 7 dias):
        // ritmo atual = 660 qty / 60 min * 60 = 660 cx/h (+10% > 5% threshold)
        $this->inserirEvento($codigoRecurso, 'PRODUCAO', now()->subDays(1)->toDateString(), 660.0, 60);

        // Act
        $component = Livewire::test(AcompanharProducao::class);

        // Assert
        $linhas = $component->get('linhas');
        $this->assertNotEmpty($linhas);

        $tendencia = $linhas[0]['historico_7d']['tendencia'];
        $this->assertSame('up', $tendencia['direcao'],
            "Variação de +10% deveria resultar em tendencia 'up'."
        );
    }

    // -------------------------------------------------------------------------
    // Test 5 — tendencia 'stable' quando variação está abaixo do threshold (< 5%)
    // -------------------------------------------------------------------------

    public function test_tendencia_stable_quando_variacao_abaixo_threshold(): void
    {
        // Arrange
        $setup         = $this->criarLinhaComProgramacaoConfirmada('LN04');
        $codigoRecurso = $setup['codigo_recurso'];

        // Semana anterior: ritmo = 603 qty / 60.3 min * 60 ≈ 600.0 cx/h
        // Usando valores simples: qty=603, min=60 → ritmo = 603/60*60 = 603 cx/h
        $this->inserirEvento($codigoRecurso, 'PRODUCAO', now()->subDays(8)->toDateString(), 603.0, 60);

        // Semana atual: qty=600, min=60 → ritmo = 600 cx/h
        // Variação = (600 - 603) / 603 ≈ -0.5% — dentro do threshold ±5%
        $this->inserirEvento($codigoRecurso, 'PRODUCAO', now()->subDays(1)->toDateString(), 600.0, 60);

        // Act
        $component = Livewire::test(AcompanharProducao::class);

        // Assert
        $linhas = $component->get('linhas');
        $this->assertNotEmpty($linhas);

        $tendencia = $linhas[0]['historico_7d']['tendencia'];
        $this->assertSame('stable', $tendencia['direcao'],
            "Variação de ~-0.5% deveria resultar em tendencia 'stable'."
        );
    }

    // -------------------------------------------------------------------------
    // Test 6 — graceful degradation sem CodiPerformance
    // -------------------------------------------------------------------------

    public function test_graceful_degradation_sem_codi_performance(): void
    {
        // Arrange — Linha com programação confirmada, mas SEM CodiPerformance
        $linha = Linha::factory()->create([
            'codigo' => 'LN07',
            'nome'   => 'Linha Sem CODI',
            'ativo'  => true,
        ]);

        $produto = Produto::firstOrCreate(
            ['sku' => 'SKU-TEST-07'],
            [
                'descricao'    => 'Produto Sem CODI',
                'taxa_por_hora' => 300.00,
                'ativo'        => true,
            ]
        );

        $programacao = Programacao::create([
            'linha_id'              => $linha->id,
            'data_inicio_planejada' => now()->subDays(1),
            'status'                => 'confirmada',
            'eficiencia'            => 100.00,
            'origem'                => 'manual',
        ]);

        ItemProgramacao::create([
            'programacao_id'   => $programacao->id,
            'sequencia'        => 1,
            'numero_op'        => 'OP-NODCODI-001',
            'sku'              => $produto->sku,
            'descricao_produto' => $produto->descricao,
            'quantidade'       => 500,
        ]);

        // Nenhum CodiPerformance inserido — $performance será null no componente

        // Act — não deve lançar exceção
        $component = Livewire::test(AcompanharProducao::class);

        // Assert — a linha ainda aparece, mas historico_7d tem zeros/null
        $linhas = $component->get('linhas');
        $this->assertNotEmpty($linhas);

        $historico = $linhas[0]['historico_7d'];
        $this->assertSame(0, $historico['producao_total'],
            "Sem CodiPerformance, producao_total deve ser 0."
        );
        $this->assertNull($historico['disponibilidade_media'],
            "Sem CodiPerformance, disponibilidade_media deve ser null."
        );
    }
}
