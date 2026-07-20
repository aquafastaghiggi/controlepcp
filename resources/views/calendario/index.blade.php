@extends('layouts.app')

@section('titulo', 'Calendário')

@section('conteudo')
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">Calendário de Trabalho</h2>
            <p class="text-sm text-gray-500 mt-0.5">Configure turnos, dias úteis e feriados por linha</p>
        </div>

        <livewire:calendario.configuracao-calendario />
    </div>
@endsection
