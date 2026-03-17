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
        array $resultRows,
        ?string $numeroOp = null
    ): int {
        $this->pdo->beginTransaction();

        $lineId = $this->ensureLine($lineCode);

        // Se a OP já existe, atualizamos a mesma programação (para evitar duplicidade)
        $programId = null;
        if ($numeroOp) {
            $stmt = $this->pdo->prepare('SELECT prg_id FROM prg_programas WHERE prg_numero_op = :op ORDER BY prg_criado_em DESC LIMIT 1');
            $stmt->execute(['op' => $numeroOp]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $programId = (int) $existing['prg_id'];
            }
        }

        if ($programId) {
            $stmt = $this->pdo->prepare(
                'UPDATE prg_programas SET prg_linha_id = :lineId, prg_base_inicio = :baseStart, prg_data_consulta = :queryDateTime, prg_eficiencia = :efficiency, prg_status = :status, prg_atualizado_em = CURRENT_TIMESTAMP WHERE prg_id = :programId'
            );

            $stmt->execute([
                'lineId' => $lineId,
                'baseStart' => $baseStart->format('Y-m-d H:i:s'),
                'queryDateTime' => $queryDateTime?->format('Y-m-d H:i:s'),
                'efficiency' => $productionEfficiency,
                'status' => 'calculado',
                'programId' => $programId,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO prg_programas (prg_numero_op, prg_linha_id, prg_base_inicio, prg_data_consulta, prg_eficiencia, prg_status) VALUES (:numeroOp, :lineId, :baseStart, :queryDateTime, :efficiency, :status)'
            );

            $stmt->execute([
                'numeroOp' => $numeroOp,
                'lineId' => $lineId,
                'baseStart' => $baseStart->format('Y-m-d H:i:s'),
                'queryDateTime' => $queryDateTime?->format('Y-m-d H:i:s'),
                'efficiency' => $productionEfficiency,
                'status' => 'calculado',
            ]);

            $programId = (int) $this->pdo->lastInsertId();
        }

        $this->saveProgramItems($programId, $programItems);
        $this->saveScheduleRows($programId, $resultRows);

        $this->pdo->commit();

        return $programId;
    }

    private function saveProgramItems(int $programId, array $items): void
    {
        // Remover itens prévios caso a mesma programação esteja sendo recalculada
        $this->pdo->prepare('DELETE FROM prg_itens WHERE prg_programa_id = :programId')->execute(['programId' => $programId]);

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
        // Sempre remover registros anteriores da mesma programação para evitar duplicações
        $this->pdo->prepare('DELETE FROM sch_linhas WHERE sch_programa_id = :programId')->execute(['programId' => $programId]);

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

    public function getAllProgramacoes(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.prg_id, p.prg_numero_op, p.prg_linha_id, l.lin_codigo, l.lin_nome, 
                    p.prg_base_inicio, p.prg_data_consulta, p.prg_eficiencia, p.prg_status,
                    p.prg_criado_em, p.prg_atualizado_em,
                    COUNT(i.prg_id_item) as total_itens
             FROM prg_programas p
             LEFT JOIN lin_linhas l ON p.prg_linha_id = l.lin_id
             LEFT JOIN prg_itens i ON p.prg_id = i.prg_programa_id
             GROUP BY p.prg_id
             ORDER BY p.prg_criado_em DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProgramacaoById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, l.lin_codigo, l.lin_nome 
             FROM prg_programas p
             LEFT JOIN lin_linhas l ON p.prg_linha_id = l.lin_id
             WHERE p.prg_id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function getProgramacaoByOp(string $op): ?array
    {
        // Sempre retornar a última programação criada para essa OP
        $stmt = $this->pdo->prepare(
            'SELECT p.*, l.lin_codigo, l.lin_nome 
             FROM prg_programas p
             LEFT JOIN lin_linhas l ON p.prg_linha_id = l.lin_id
             WHERE p.prg_numero_op = :op
             ORDER BY p.prg_criado_em DESC
             LIMIT 1'
        );
        $stmt->execute(['op' => $op]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function getProgramacaoItens(int $programId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM prg_itens WHERE prg_programa_id = :programId ORDER BY prg_sequencia ASC'
        );
        $stmt->execute(['programId' => $programId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProgramacaoSchedule(int $programId): array
    {
        // Retorna apenas a última execução (batch) para essa programacao, evitando mostrar linhas antigas duplicadas.
        // Note: MySQL native prepared statements don't allow reusing the same named parameter twice, so we use two names.
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM sch_linhas
             WHERE sch_programa_id = :programId
               AND sch_criado_em = (
                 SELECT MAX(sch_criado_em)
                 FROM sch_linhas
                 WHERE sch_programa_id = :programIdMax
               )
             ORDER BY sch_sequencia ASC'
        );
        $stmt->execute(['programId' => $programId, 'programIdMax' => $programId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createProgramacao(
        ?string $numeroOp,
        string $lineCode,
        DateTimeImmutable $baseStart,
        ?DateTimeImmutable $queryDateTime = null,
        float $efficiency = 100,
        string $status = 'rascunho'
    ): int {
        $lineId = $this->ensureLine($lineCode);

        // Verificar se número da OP já existe
        if ($numeroOp) {
            $stmt = $this->pdo->prepare('SELECT prg_id FROM prg_programas WHERE prg_numero_op = :op LIMIT 1');
            $stmt->execute(['op' => $numeroOp]);
            if ($stmt->fetch()) {
                throw new \Exception("Número da OP {$numeroOp} já existe.");
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO prg_programas (prg_numero_op, prg_linha_id, prg_base_inicio, prg_data_consulta, prg_eficiencia, prg_status) 
             VALUES (:numeroOp, :lineId, :baseStart, :queryDateTime, :efficiency, :status)'
        );

        $stmt->execute([
            'numeroOp' => $numeroOp,
            'lineId' => $lineId,
            'baseStart' => $baseStart->format('Y-m-d H:i:s'),
            'queryDateTime' => $queryDateTime?->format('Y-m-d H:i:s'),
            'efficiency' => $efficiency,
            'status' => $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateProgramacao(
        int $programId,
        ?string $numeroOp = null,
        ?DateTimeImmutable $baseStart = null,
        ?DateTimeImmutable $queryDateTime = null,
        ?float $efficiency = null,
        ?string $status = null
    ): void {
        // Se está tentando atualizar o OP, verificar se já existe outro com esse OP
        if ($numeroOp !== null) {
            $stmt = $this->pdo->prepare('SELECT prg_id FROM prg_programas WHERE prg_numero_op = :op AND prg_id != :programId LIMIT 1');
            $stmt->execute(['op' => $numeroOp, 'programId' => $programId]);
            if ($stmt->fetch()) {
                throw new \Exception("Número da OP {$numeroOp} já existe.");
            }
        }

        $updates = [];
        $bind = ['programId' => $programId];

        if ($numeroOp !== null) {
            $updates[] = 'prg_numero_op = :numeroOp';
            $bind['numeroOp'] = $numeroOp;
        }

        if ($baseStart !== null) {
            $updates[] = 'prg_base_inicio = :baseStart';
            $bind['baseStart'] = $baseStart->format('Y-m-d H:i:s');
        }

        if ($queryDateTime !== null) {
            $updates[] = 'prg_data_consulta = :queryDateTime';
            $bind['queryDateTime'] = $queryDateTime->format('Y-m-d H:i:s');
        }

        if ($efficiency !== null) {
            $updates[] = 'prg_eficiencia = :efficiency';
            $bind['efficiency'] = $efficiency;
        }

        if ($status !== null) {
            $updates[] = 'prg_status = :status';
            $bind['status'] = $status;
        }

        if (empty($updates)) {
            return;
        }

        $sql = 'UPDATE prg_programas SET ' . implode(', ', $updates) . ' WHERE prg_id = :programId';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bind);
    }

    public function deleteProgramacao(int $programId): void
    {
        // Isso vai deletar items e schedule em cascata por causa da FK
        $stmt = $this->pdo->prepare('DELETE FROM prg_programas WHERE prg_id = :programId');
        $stmt->execute(['programId' => $programId]);
    }
}
