<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;
use App\Support\DateTimeHelper;
use DateTimeImmutable;
use PDO;

final class ProgramacaoRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    public function salvarExecucao(
        string $lineCode,
        DateTimeImmutable $baseStart,
        ?DateTimeImmutable $queryDateTime,
        float $productionEfficiency,
        array $programItems,
        array $resultRows
    ): int {
        $this->pdo->beginTransaction();

        $lineId = $this->ensureLine($lineCode);

        $stmt = $this->pdo->prepare(
            'INSERT INTO prg_programas (prg_linha_id, prg_base_inicio, prg_data_consulta, prg_eficiencia, prg_status) VALUES (:lineId, :baseStart, :queryDateTime, :efficiency, :status)'
        );

        $stmt->execute([
            'lineId' => $lineId,
            'baseStart' => $baseStart->format('Y-m-d H:i:s'),
            'queryDateTime' => $queryDateTime?->format('Y-m-d H:i:s'),
            'efficiency' => $productionEfficiency,
            'status' => 'calculado',
        ]);

        $programId = (int) $this->pdo->lastInsertId();

        $this->saveProgramItems($programId, $programItems);
        $this->saveScheduleRows($programId, $resultRows);

        $this->pdo->commit();

        return $programId;
    }

    private function saveProgramItems(int $programId, array $items): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO prg_itens (prg_programa_id, prg_sequencia, prg_sku, prg_quantidade, prg_inicio_planejado) VALUES (:programId, :sequence, :sku, :quantity, :plannedStart)'
        );

        foreach ($items as $item) {
            $planned = DateTimeHelper::fromLocalInput((string) ($item['planned_start'] ?? ''));

            $stmt->execute([
                'programId' => $programId,
                'sequence' => (int) ($item['sequence'] ?? 0),
                'sku' => (string) ($item['sku'] ?? ''),
                'quantity' => (float) ($item['quantity'] ?? 0),
                'plannedStart' => $planned?->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function saveScheduleRows(int $programId, array $rows): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sch_linhas (
                sch_programa_id,
                sch_tipo,
                sch_sequencia,
                sch_sku,
                sch_descricao,
                sch_quantidade,
                sch_taxa_por_hora,
                sch_duracao_minutos,
                sch_sku_anterior,
                sch_inicio_planejado,
                sch_data_inicio,
                sch_hora_inicio,
                sch_hora_fim,
                sch_inicio_producao,
                sch_fim_producao,
                sch_produzido_estimado,
                sch_status,
                sch_memoria_calculo
            ) VALUES (
                :programId,
                :tipo,
                :sequencia,
                :sku,
                :descricao,
                :quantidade,
                :taxaPorHora,
                :duracaoMinutos,
                :skuAnterior,
                :inicioPlanejado,
                :dataInicio,
                :horaInicio,
                :horaFim,
                :inicioProducao,
                :fimProducao,
                :produzidoEstimado,
                :status,
                :memoriaCalculo
            )'
        );

        foreach ($rows as $row) {
            $duration = (string) ($row['duration_label'] ?? '');
            $minutes = $duration !== '' ? DateTimeHelper::minutesFromDuration($duration) : null;

            $productionStart = DateTimeHelper::fromLocalInput((string) ($row['production_start'] ?? ''));
            $productionEnd = DateTimeHelper::fromLocalInput((string) ($row['production_end'] ?? ''));

            $dataInicio = (string) ($row['date_start'] ?? '');
            $horaInicio = (string) ($row['time_start'] ?? '');
            $horaFim = (string) ($row['time_end'] ?? '');

            $dataInicioYmd = '';
            if ($dataInicio !== '') {
                $parsed = \DateTimeImmutable::createFromFormat('d/m/Y', $dataInicio);
                $dataInicioYmd = $parsed ? $parsed->format('Y-m-d') : '';
            }

            $stmt->execute([
                'programId' => $programId,
                'tipo' => (string) ($row['type'] ?? ''),
                'sequencia' => (int) ($row['sequence'] ?? 0),
                'sku' => $row['sku'] && $row['sku'] !== 'SETUP' ? (string) $row['sku'] : null,
                'descricao' => (string) ($row['description'] ?? ''),
                'quantidade' => $row['quantity'] !== null ? (float) $row['quantity'] : null,
                'taxaPorHora' => $row['rate_per_hour'] !== null ? (float) $row['rate_per_hour'] : null,
                'duracaoMinutos' => $minutes,
                'skuAnterior' => $row['previous_sku'] ? (string) $row['previous_sku'] : null,
                'inicioPlanejado' => $this->formatDateTimeOrNull((string) ($row['planned_start'] ?? '')),
                'dataInicio' => $dataInicioYmd ?: null,
                'horaInicio' => $horaInicio ?: null,
                'horaFim' => $horaFim ?: null,
                'inicioProducao' => $productionStart?->format('Y-m-d H:i:s'),
                'fimProducao' => $productionEnd?->format('Y-m-d H:i:s'),
                'produzidoEstimado' => $row['estimated_produced'] !== null ? (float) $row['estimated_produced'] : null,
                'status' => (string) ($row['status'] ?? ''),
                'memoriaCalculo' => (string) ($row['calculation_memory'] ?? ''),
            ]);
        }
    }

    private function ensureLine(string $code): int
    {
        $stmt = $this->pdo->prepare('SELECT lin_id FROM lin_linhas WHERE lin_codigo = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $line = $stmt->fetch();

        if ($line) {
            return (int) $line['lin_id'];
        }

        $stmt = $this->pdo->prepare('INSERT INTO lin_linhas (lin_codigo, lin_nome) VALUES (:code, :name)');
        $stmt->execute(['code' => $code, 'name' => sprintf('Linha %s', $code)]);

        return (int) $this->pdo->lastInsertId();
    }

    private function formatDateTimeOrNull(string $value): ?string
    {
        $dt = DateTimeHelper::fromLocalInput($value);

        return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }
}
