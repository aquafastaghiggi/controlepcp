<?php

declare(strict_types=1);

namespace App\Livewire\Programacao;

use App\Models\Programacao;
use Livewire\Component;

/**
 * Componente de Gantt para visualização do resultado do sequenciamento.
 *
 * Recebe o ID da programação e prepara os dados para o Chart.js no frontend.
 * Diferencia visualmente setup (laranja) e produção (azul) com duração em horas.
 */
class GraficoGantt extends Component
{
    public int $programacaoId;

    protected $listeners = ['programacao-calculada' => 'carregarProgramacao'];

    public function carregarProgramacao(int $id): void
    {
        $this->programacaoId = $id;
    }

    public function render()
    {
        $programacao = null;
        $dadosGantt  = [];

        if ($this->programacaoId) {
            $programacao = Programacao::with(['resultados', 'linha'])
                ->find($this->programacaoId);

            if ($programacao) {
                $dadosGantt = $this->prepararDadosGantt($programacao);
            }
        }

        return view('livewire.programacao.grafico-gantt', compact('programacao', 'dadosGantt'));
    }

    /**
     * Transforma os resultados em estrutura compatível com Chart.js (Bar horizontal).
     * Cada barra representa um bloco de tempo (setup ou produção).
     */
    private function prepararDadosGantt(Programacao $programacao): array
    {
        $labels   = [];
        $datasets = [
            'setup'   => ['label' => 'Setup', 'backgroundColor' => '#F59E0B', 'data' => []],
            'producao'=> ['label' => 'Produção', 'backgroundColor' => '#3B82F6', 'data' => []],
        ];

        foreach ($programacao->resultados->sortBy('inicio') as $resultado) {
            $label = $resultado->ehSetup()
                ? "Setup → {$resultado->sku}"
                : "{$resultado->sku}";

            $labels[] = $label;

            $inicio      = $resultado->inicio->getTimestamp() * 1000; // ms para Chart.js
            $duracaoMs   = $resultado->duracao_minutos * 60 * 1000;

            $datasets[$resultado->tipo]['data'][] = [
                'x' => $inicio,
                'y' => $label,
                'duracao_minutos' => $resultado->duracao_minutos,
                'memoria_calculo' => $resultado->memoria_calculo,
            ];
        }

        return [
            'labels'   => $labels,
            'datasets' => array_values($datasets),
        ];
    }
}
