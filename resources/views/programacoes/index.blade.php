@extends('layouts.app')

@section('titulo', 'Programação')

@section('conteudo')
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">Nova Programação</h2>
            <p class="text-sm text-gray-500 mt-0.5">Monte as ordens, otimize a sequência e calcule o Gantt</p>
        </div>

        <livewire:programacao.formulario-programacao />
    </div>
@endsection
