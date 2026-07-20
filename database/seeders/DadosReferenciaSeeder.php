<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Calendario;
use App\Models\DiaUtil;
use App\Models\Feriado;
use App\Models\Intervalo;
use App\Models\Linha;
use App\Models\MatrizSetup;
use App\Models\Produto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed de referência com os dados da Linha L2 da versão anterior.
 *
 * Cria:
 * - 1 linha de produção (L2)
 * - 1 calendário padrão com 4 turnos (incluindo noturno)
 * - 5 produtos com suas taxas por hora
 * - Matriz de setup completa entre todos os produtos
 *
 * Idempotente: pode ser executado múltiplas vezes sem duplicar dados.
 * Usa updateOrCreate em todos os registros.
 */
class DadosReferenciaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $linha      = $this->criarLinha();
            $calendario = $this->criarCalendario($linha);
            $turnos     = $this->criarTurnos($calendario);
            $this->criarDiasUteis($turnos);
            $produtos   = $this->criarProdutos();
            $this->criarMatrizSetup($produtos);
        });

        $this->command->info('✓ Linha L2 criada/atualizada');
        $this->command->info('✓ Calendário padrão com 4 turnos configurado');
        $this->command->info('✓ 5 produtos cadastrados');
        $this->command->info('✓ Matriz de setup gerada');
    }

    // ─── Linha ───────────────────────────────────────────────────────────────

    private function criarLinha(): Linha
    {
        return Linha::updateOrCreate(
            ['codigo' => 'L2'],
            [
                'nome'  => 'Linha 2 — Envase',
                'ativo' => true,
            ]
        );
    }

    // ─── Calendário ──────────────────────────────────────────────────────────

    private function criarCalendario(Linha $linha): Calendario
    {
        return Calendario::updateOrCreate(
            ['linha_id' => $linha->id],
            ['nome' => 'Calendário Padrão L2']
        );
    }

    // ─── Turnos ───────────────────────────────────────────────────────────────

    /**
     * Cria os 4 turnos da Linha L2.
     *
     * Turno 1 (Matutino):  07:10–11:28
     * Turno 2 (Vespertino): 13:35–17:40
     * Turno 3 (Noturno 1): 17:40–22:00  — opcional (pode ser desativado)
     * Turno 4 (Noturno 2): 23:00–03:00  — overnight, Seg–Qui
     *
     * @return array<string, Intervalo>  chaves: 'turno1', 'turno2', 'turno3', 'turno4'
     */
    private function criarTurnos(Calendario $calendario): array
    {
        $turno1 = Intervalo::updateOrCreate(
            ['calendario_id' => $calendario->id, 'hora_inicio' => '07:10', 'hora_fim' => '11:28'],
            ['nome' => 'Turno 1', 'ordem' => 1, 'ativo' => true]
        );

        $turno2 = Intervalo::updateOrCreate(
            ['calendario_id' => $calendario->id, 'hora_inicio' => '13:35', 'hora_fim' => '17:40'],
            ['nome' => 'Turno 2', 'ordem' => 2, 'ativo' => true]
        );

        // Turno 3 começa onde o Turno 2 termina — sem intervalo entre eles
        $turno3 = Intervalo::updateOrCreate(
            ['calendario_id' => $calendario->id, 'hora_inicio' => '17:40', 'hora_fim' => '22:00'],
            ['nome' => 'Turno 3', 'ordem' => 3, 'ativo' => true]
        );

        // Turno noturno overnight: 23:00 do dia D até 03:00 do dia D+1
        $turno4 = Intervalo::updateOrCreate(
            ['calendario_id' => $calendario->id, 'hora_inicio' => '23:00', 'hora_fim' => '03:00'],
            ['nome' => 'Turno 4 (Noturno)', 'ordem' => 4, 'ativo' => true]
        );

        return [
            'turno1' => $turno1,
            'turno2' => $turno2,
            'turno3' => $turno3,
            'turno4' => $turno4,
        ];
    }

    /**
     * Define os dias da semana para cada turno.
     *
     * Turnos 1–3: Segunda a Sexta (dias 1–5)
     * Turno 4:    Segunda a Quinta (dias 1–4) — não funciona na noite de Sexta
     */
    private function criarDiasUteis(array $turnos): void
    {
        $diasSegSex  = [1, 2, 3, 4, 5]; // Segunda a Sexta
        $diasSegQui  = [1, 2, 3, 4];    // Segunda a Quinta (noturno)

        $this->vincularDias($turnos['turno1'], $diasSegSex);
        $this->vincularDias($turnos['turno2'], $diasSegSex);
        $this->vincularDias($turnos['turno3'], $diasSegSex);
        $this->vincularDias($turnos['turno4'], $diasSegQui);
    }

    private function vincularDias(Intervalo $turno, array $dias): void
    {
        foreach ($dias as $dia) {
            DiaUtil::updateOrCreate(
                ['intervalo_id' => $turno->id, 'dia_semana' => $dia]
            );
        }
    }

    // ─── Produtos ─────────────────────────────────────────────────────────────

    /**
     * Cria os produtos de referência com suas taxas de produção (cx/hora).
     *
     * Taxas baseadas na v1 (Linha L2 real):
     * - Água Sanitária 5L e Alvejante 5L: 180 cx/h (embalagem maior, mais lenta)
     * - Água Sanitária 2L e Desinfetantes: 195–200 cx/h
     *
     * referencia_setup agrupa por família para facilitar a leitura da matriz.
     *
     * @return array<string, Produto>  chaves: código do SKU
     */
    private function criarProdutos(): array
    {
        $definicoes = [
            'AS5L' => [
                'descricao'        => 'Água Sanitária 5L',
                'taxa_por_hora'    => 180.0,
                'referencia_setup' => 'AGUA_SANITARIA',
            ],
            'AS2L' => [
                'descricao'        => 'Água Sanitária 2L',
                'taxa_por_hora'    => 195.0,
                'referencia_setup' => 'AGUA_SANITARIA',
            ],
            'DS1L' => [
                'descricao'        => 'Desinfetante 1L',
                'taxa_por_hora'    => 200.0,
                'referencia_setup' => 'DESINFETANTE',
            ],
            'DS500ML' => [
                'descricao'        => 'Desinfetante 500ml',
                'taxa_por_hora'    => 200.0,
                'referencia_setup' => 'DESINFETANTE',
            ],
            'ALV5L' => [
                'descricao'        => 'Alvejante 5L',
                'taxa_por_hora'    => 180.0,
                'referencia_setup' => 'ALVEJANTE',
            ],
        ];

        $produtos = [];

        foreach ($definicoes as $sku => $dados) {
            $produtos[$sku] = Produto::updateOrCreate(
                ['sku' => $sku],
                array_merge($dados, ['ativo' => true])
            );
        }

        return $produtos;
    }

    // ─── Matriz de Setup ─────────────────────────────────────────────────────

    /**
     * Cria a matriz de setup com as regras de tempo de troca:
     *
     *   Desinfetante → Desinfetante : 20 minutos (mesma família, limpeza simples)
     *   Água Sanitária ↔ qualquer   : 30 minutos (produto com hipoclorito, limpeza rigorosa)
     *   Alvejante ↔ qualquer        : 30 minutos (mesma razão de limpeza)
     *
     * Diagonal (mesmo produto → mesmo produto) não é cadastrada (não há setup).
     */
    private function criarMatrizSetup(array $produtos): void
    {
        $skus = array_keys($produtos);

        foreach ($skus as $origem) {
            foreach ($skus as $destino) {
                if ($origem === $destino) {
                    continue; // sem setup para o mesmo produto
                }

                $duracao = $this->determinarDuracaoSetup($origem, $destino);

                MatrizSetup::updateOrCreate(
                    ['sku_origem' => $origem, 'sku_destino' => $destino],
                    ['duracao_minutos' => $duracao]
                );
            }
        }
    }

    /**
     * Determina o tempo de setup entre dois SKUs com base na família do produto.
     *
     * Regra: se qualquer um dos dois pertence a uma família "pesada" (AS ou ALV),
     * o setup é mais longo por exigir limpeza mais rigorosa da linha.
     */
    private function determinarDuracaoSetup(string $skuOrigem, string $skuDestino): int
    {
        // Prefixos de produtos que exigem setup mais longo (30 min)
        $prefixosPesados = ['AS', 'ALV'];

        foreach ($prefixosPesados as $prefixo) {
            if (str_starts_with($skuOrigem, $prefixo) || str_starts_with($skuDestino, $prefixo)) {
                return 30;
            }
        }

        // Desinfetantes entre si — limpeza simples
        return 20;
    }
}
