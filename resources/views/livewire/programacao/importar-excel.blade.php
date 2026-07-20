<div>
    <label class="block text-xs font-semibold text-gray-600 mb-2">
        Arquivo Excel do PCP (.xlsx ou .xls)
    </label>
    <input type="file" wire:model="arquivo" accept=".xlsx,.xls"
           class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4
                  file:rounded-lg file:border-0 file:text-sm file:font-medium
                  file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100
                  border border-gray-300 rounded-lg px-3 py-2 cursor-pointer">

    @error('arquivo')
        <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
    @enderror

    <div wire:loading wire:target="arquivo"
         class="mt-2 text-xs text-blue-600 flex items-center gap-1.5">
        <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10"
                    stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
        Processando planilha...
    </div>

    @if($erro)
        <div class="mt-3 px-4 py-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
            {{ $erro }}
        </div>
    @endif
</div>
