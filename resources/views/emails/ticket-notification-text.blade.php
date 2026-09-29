Hello {{ $recipient->first_name ?? $recipient->name ?? 'there' }},

{{ $title }}

--------------------------------------------------
{{ $contentMessage }}
--------------------------------------------------

@if($ticket)
Ticket Subject: {{ $ticket->subject }}
Ticket #: #{{ $ticket->id }}
Priority: {{ $ticket->priority?->value ?? 'MEDIUM' }}
Status: {{ $ticket->status?->value ?? 'OPEN' }}
@elseif(!empty($context['ticket_id']))
Ticket ID: #{{ $context['ticket_id'] }}
@if(!empty($context['status']))
Status: {{ $context['status'] }}
@endif
@endif

@php
    $ticketId = $ticket->id ?? $context['ticket_id'] ?? null;
    $targetUrl = $ticketId ? "{$appUrl}/tickets/{$ticketId}" : $appUrl;
@endphp
View your ticket in Help Desk: {{ $targetUrl }}

--------------------------------------------------
This is an automated notification from {{ config('app.name', 'Help Desk') }}.
Please do not reply directly to this automated email.
