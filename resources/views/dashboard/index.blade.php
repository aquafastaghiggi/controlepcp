@extends('layouts.app')

@section('titulo', 'Painel')

@section('conteudo')
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">Painel</h2>
            <p class="text-sm text-gray-500 mt-0.5">Visão geral da produção</p>
        </div>

        <livewire:dashboard.painel-principal />
    </div>
@endsection
