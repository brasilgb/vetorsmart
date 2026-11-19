<?php

namespace App\Enums;

/**
 * Operational notices of a company. Every notice appears in the panel. The e-mail copy can be turned off by each
 * person for the optional categories; administrative notices are essential and always sent. There is no commercial
 * or promotional category: the platform does not send marketing through this channel.
 */
enum NotificationCategory: string
{
    case RequestReceived = 'request_received';
    case RequestAssigned = 'request_assigned';
    case AppointmentConfirmed = 'appointment_confirmed';
    case AppointmentCancelled = 'appointment_cancelled';
    case AwaitingAttendance = 'awaiting_attendance';
    case AdminNotice = 'admin_notice';

    public function label(): string
    {
        return match ($this) {
            self::RequestReceived => 'Nova solicitação recebida',
            self::RequestAssigned => 'Solicitação atribuída a você',
            self::AppointmentConfirmed => 'Agendamento confirmado',
            self::AppointmentCancelled => 'Agendamento cancelado',
            self::AwaitingAttendance => 'Conversa aguardando atendimento',
            self::AdminNotice => 'Avisos da administração',
        };
    }

    /**
     * Whether the e-mail copy is essential and cannot be turned off.
     */
    public function isMandatory(): bool
    {
        return $this === self::AdminNotice;
    }

    /**
     * Categories whose e-mail each person may turn off.
     *
     * @return list<self>
     */
    public static function optional(): array
    {
        return array_values(array_filter(self::cases(), fn (self $category): bool => ! $category->isMandatory()));
    }
}
