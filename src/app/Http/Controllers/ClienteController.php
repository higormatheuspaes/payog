<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\ClienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function __construct(private ClienteService $service) {}

    public function index(Request $request): View
    {
        $clientes = $this->service->listar(
            $request->string('busca'),
            $request->string('categoria')
        );

        return view('clientes.index', compact('clientes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'nome'     => ['required', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:20'],
            'cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'email'    => ['nullable', 'email', 'max:255'],
        ]);

        $this->service->criar($dados);

        return back()->with('success', 'Cliente criado com sucesso.');
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        abort_if($cliente->empresa_id !== auth()->user()->empresa_id, 403);

        $dados = $request->validate([
            'nome'     => ['required', 'string', 'max:255'],
            'telefone' => ['required', 'string', 'max:20'],
            'cpf_cnpj' => ['nullable', 'string', 'max:18'],
            'email'    => ['nullable', 'email', 'max:255'],
        ]);

        $this->service->atualizar($cliente, $dados);

        return back()->with('success', 'Cliente atualizado com sucesso.');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        abort_if($cliente->empresa_id !== auth()->user()->empresa_id, 403);

        try {
            $this->service->excluir($cliente);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['excluir' => $e->getMessage()]);
        }

        return back()->with('success', 'Cliente excluído com sucesso.');
    }
}
