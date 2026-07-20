@extends('layouts.app')

@section('titulo', 'Histórico')

@section('conteudo')
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">Histórico de Programações</h2>
            <p class="text-sm text-gray-500 mt-0.5">Programações calculadas, confirmadas e canceladas, com filtro por linha</p>
        </div>

        <livewire:programacao.lista-programacoes />
    </div>
@endsection
