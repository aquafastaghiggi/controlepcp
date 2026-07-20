@extends('layouts.app')

@section('titulo', 'Acompanhar Produção')

@section('conteudo')

<div class="mb-6">
    <h1 class="text-xl font-bold text-gray-800">Acompanhar Produção</h1>
    <p class="text-sm text-gray-500 mt-1">Monitoramento em tempo real das linhas de produção ativas</p>
</div>

<livewire:dashboard.acompanhar-producao />

@endsection
