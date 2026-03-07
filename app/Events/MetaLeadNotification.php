<?php

namespace App\Events;

use App\Models\CrmLead;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MetaLeadNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $lead;
    public $agentId;
    public $notificationType;
    public $message;

    public function __construct(CrmLead $lead, int $agentId, string $notificationType, string $message)
    {
        $this->lead = $lead;
        $this->agentId = $agentId;
        $this->notificationType = $notificationType; // 'new_lead', 'message_received', 'campaign_response'
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("agent.{$this->agentId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return "meta.notification.{$this->notificationType}";
    }
}
