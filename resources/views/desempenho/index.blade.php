@extends('layouts.app')

@section('titulo', 'Desempenho')

@section('conteudo')

<div class="mb-6">
    <h1 class="text-xl font-bold text-gray-800">Desempenho de Produção</h1>
    <p class="text-sm text-gray-500 mt-1">KPIs e eficiência por programação — previsto × realizado (CODI)</p>
</div>

<livewire:desempenho.painel-desempenho />

@endsection
