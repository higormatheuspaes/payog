<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertaConsumoMensagens extends Notification implements ShouldQueue
{
    use Queueable;

    public const TIPO_80_PORCENTO = '80_porcento';
    public const TIPO_EXCEDENTE   = 'excedente';
    public const TIPO_TETO        = 'teto';

    public function __construct(
        private readonly string $tipo,
        private readonly int    $enviadas,
        private readonly int    $limite,
        private readonly float  $valorExcedente = 0.0,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->tipo) {
            self::TIPO_80_PORCENTO => $this->mail80Porcento($notifiable),
            self::TIPO_EXCEDENTE   => $this->mailExcedente($notifiable),
            self::TIPO_TETO        => $this->mailTeto($notifiable),
        };
    }

    private function mail80Porcento(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Payog] Você atingiu 80% do limite de mensagens')
            ->greeting('Atenção, ' . $notifiable->name . '!')
            ->line("Sua empresa já usou **{$this->enviadas} de {$this->limite} mensagens** incluídas no plano este mês.")
            ->line('Ao ultrapassar o limite, cada mensagem adicional será cobrada como excedente (R$ 0,20/mensagem).')
            ->action('Ver consumo no painel', url('/configuracoes'))
            ->line('Para evitar cobranças extras, considere fazer upgrade de plano.');
    }

    private function mailExcedente(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Payog] Você entrou em regime de excedente')
            ->greeting('Aviso importante, ' . $notifiable->name . '!')
            ->line("Seu limite de **{$this->limite} mensagens/mês** foi atingido.")
            ->line('As mensagens enviadas a partir de agora serão cobradas como excedente a R$ 0,20 cada.')
            ->line("Excedente acumulado até o momento: **R$ " . number_format($this->valorExcedente, 2, ',', '.') . "**.")
            ->action('Gerenciar plano', url('/assinatura/planos'))
            ->line('Você pode configurar um teto de gasto em Configurações → Notificações.');
    }

    private function mailTeto(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Payog] Teto de gasto com excedente atingido — envios pausados')
            ->greeting('Aviso, ' . $notifiable->name . '!')
            ->line("O teto de gasto com mensagens excedentes foi atingido (R$ " . number_format($this->valorExcedente, 2, ',', '.') . ").")
            ->line('**Todos os envios automáticos foram pausados** até o início do próximo ciclo de faturamento.')
            ->line('Seus clientes não receberão lembretes ou confirmações de pagamento até lá.')
            ->action('Ajustar configurações', url('/configuracoes'))
            ->line('Para reativar os envios agora, aumente o teto de gasto ou faça upgrade de plano.');
    }
}
