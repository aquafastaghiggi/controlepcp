@extends('layouts.app')

@section('titulo', 'Ordens de Produção')

@section('conteudo')
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">Ordens de Produção</h2>
            <p class="text-sm text-gray-500 mt-0.5">Gestão de ordens de produção</p>
        </div>

        <livewire:ordem-producao.gerenciar-ordens />
    </div>
@endsection
