<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrdemProducao;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class OrdemProducaoService
{
    public function criar(array $dados): OrdemProducao
    {
        $validator = Validator::make($dados, [
            'sku'               => ['required', 'string', 'max:100'],
            'descricao_produto' => ['required', 'string', 'max:255'],
            'quantidade'        => ['required', 'numeric', 'min:0.001'],
            'linha_id'          => ['nullable', 'exists:linhas,id'],
            'numero_op'         => ['nullable', 'string', 'max:50', 'unique:ordens_producao,numero_op'],
            'data_entrega'      => ['nullable', 'date'],
            'prioridade'        => ['nullable', 'integer', 'between:1,10'],
            'origem'            => ['nullable', 'in:manual,excel,api_erp'],
            'observacoes'       => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($dados) {
            $gerarNumero = empty($dados['numero_op']);

            $ordem = OrdemProducao::create([
                'sku'               => $dados['sku'],
                'descricao_produto' => $dados['descricao_produto'],
                'quantidade'        => $dados['quantidade'],
                'linha_id'          => $dados['linha_id'] ?? null,
                'numero_op'         => $dados['numero_op'] ?? null,
                'data_entrega'      => $dados['data_entrega'] ?? null,
                'prioridade'        => $dados['prioridade'] ?? 5,
                'origem'            => $dados['origem'] ?? 'manual',
                'observacoes'       => $dados['observacoes'] ?? null,
            ]);

            if ($gerarNumero) {
                $ordem->update([
                    'numero_op' => 'OP' . str_pad((string) $ordem->id, 6, '0', STR_PAD_LEFT),
                ]);
            }

            return $ordem->fresh(['linha']);
        });
    }

    public function atualizarStatus(OrdemProducao $ordem, string $novoStatus): OrdemProducao
    {
        $permitidos = OrdemProducao::TRANSICOES_POSSIVEIS[$ordem->status] ?? [];

        if (!in_array($novoStatus, $permitidos, true)) {
            throw new InvalidArgumentException("Transição inválida: {$ordem->status} → {$novoStatus}");
        }

        $ordem->update(['status' => $novoStatus]);

        return $ordem->fresh();
    }

    public function listar(array $filtros = []): LengthAwarePaginator
    {
        $porPagina = (int) ($filtros['por_pagina'] ?? 20);

        return OrdemProducao::with('linha')
            ->when(
                isset($filtros['status']),
                fn ($q) => $q->where('status', $filtros['status'])
            )
            ->when(
                isset($filtros['linha_id']),
                fn ($q) => $q->where('linha_id', $filtros['linha_id'])
            )
            ->when(
                isset($filtros['busca']) && $filtros['busca'] !== '',
                fn ($q) => $q->where(fn ($sub) => $sub
                    ->orWhere('numero_op', 'like', '%' . $filtros['busca'] . '%')
                    ->orWhere('sku', 'like', '%' . $filtros['busca'] . '%')
                    ->orWhere('descricao_produto', 'like', '%' . $filtros['busca'] . '%')
                )
            )
            ->orderByRaw("CASE status WHEN 'em_producao' THEN 1 WHEN 'pendente' THEN 2 WHEN 'programada' THEN 3 WHEN 'concluida' THEN 4 WHEN 'cancelada' THEN 5 ELSE 6 END")
            ->orderBy('data_entrega')
            ->orderBy('prioridade')
            ->paginate($porPagina);
    }
}
