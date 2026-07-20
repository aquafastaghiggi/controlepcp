@extends('layouts.app')

@section('titulo', 'Produtos')

@section('conteudo')
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">Produtos e Matriz de Setup</h2>
            <p class="text-sm text-gray-500 mt-0.5">Taxas de produção e tempos de troca entre SKUs</p>
        </div>

        <livewire:produto.gerenciar-produtos />
    </div>
@endsection
