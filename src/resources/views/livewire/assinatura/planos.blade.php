<?php

use App\Models\Plano;
use App\Services\AbacatePayService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public int $plano_id_atual  = 0;
    public int $plano_id_novo   = 0;
    public string $mensagem     = '';
    public string $mensagemTipo = 'sucesso';

    public function mount(): void
    {
        $this->plano_id_atual = auth()->user()->empresa->plano_id ?? 0;
        $this->plano_id_novo  = $this->plano_id_atual;
    }

    public function with(): array
    {
        return ['planos' => Plano::orderBy('valor_mensal')->get()];
    }

    public function mudarPlano(): void
    {
        if ($this->plano_id_novo === $this->plano_id_atual) {
            $this->mensagem     = 'Você já está neste plano.';
            $this->mensagemTipo = 'erro';
            return;
        }

        $empresa  = auth()->user()->empresa;
        $novoPlano = Plano::find($this->plano_id_novo);

        if (! $novoPlano?->abacatepay_product_id) {
            $this->mensagem     = 'Plano inválido.';
            $this->mensagemTipo = 'erro';
            return;
        }

        if (! $empresa->abacatepay_subscription_id) {
            $this->mensagem     = 'Nenhuma assinatura ativa encontrada.';
            $this->mensagemTipo = 'erro';
            return;
        }

        try {
            $resultado = (new AbacatePayService)->mudarPlano(
                $empresa->abacatepay_subscription_id,
                $novoPlano->abacatepay_product_id,
            );

            Log::info('AbacatePay change-plan resposta', ['resultado' => $resultado]);

            $empresa->update(['plano_id' => $novoPlano->id]);
            $this->plano_id_atual = $novoPlano->id;

            $this->mensagem     = 'Plano alterado para ' . $novoPlano->nome . '. O novo valor será cobrado no próximo ciclo.';
            $this->mensagemTipo = 'sucesso';
        } catch (\Exception $e) {
            Log::error('AbacatePay mudar plano falhou', [
                'empresa_id' => $empresa->id,
                'error'      => $e->getMessage(),
            ]);
            $this->mensagem     = 'Não foi possível alterar o plano. Tente novamente.';
            $this->mensagemTipo = 'erro';
        }
    }
}; ?>

<div>
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('configuracoes.index') }}" wire:navigate
            class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Escolher plano</h1>
            <p class="text-sm text-gray-500 mt-0.5">A mudança entra em vigor no próximo ciclo de cobrança</p>
        </div>
    </div>

    @if($mensagem)
        <div class="mb-6 px-4 py-3 rounded-lg text-sm font-medium {{ $mensagemTipo === 'sucesso' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
            {{ $mensagem }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @foreach($planos as $plano)
            @php
                $atual   = $plano->id === $plano_id_atual;
                $selecionado = $plano->id === $plano_id_novo;
            @endphp
            <button wire:click="$set('plano_id_novo', {{ $plano->id }})"
                class="relative text-left p-5 rounded-xl border-2 transition-all
                    {{ $selecionado ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 bg-white hover:border-gray-300' }}">

                @if($atual)
                    <span class="absolute top-3 right-3 text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700">
                        Atual
                    </span>
                @endif

                <p class="text-sm font-semibold {{ $selecionado ? 'text-indigo-700' : 'text-gray-900' }} mb-1">
                    {{ $plano->nome }}
                </p>
                <p class="text-2xl font-bold {{ $selecionado ? 'text-indigo-600' : 'text-gray-900' }}">
                    R$ {{ number_format($plano->valor_mensal, 2, ',', '.') }}
                    <span class="text-sm font-normal text-gray-400">/mês</span>
                </p>
            </button>
        @endforeach
    </div>

    <div class="flex items-center gap-3">
        <button wire:click="mudarPlano" wire:loading.attr="disabled"
            @if($plano_id_novo === $plano_id_atual) disabled @endif
            class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="mudarPlano">Confirmar mudança de plano</span>
            <span wire:loading wire:target="mudarPlano">Alterando...</span>
        </button>
        <a href="{{ route('configuracoes.index') }}" wire:navigate
            class="px-5 py-2.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
            Cancelar
        </a>
    </div>
</div>
