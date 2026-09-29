<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Ticket\Actions\AddTicketMessageAction;
use App\Domain\Ticket\Actions\AssignTicketAction;
use App\Domain\Ticket\Actions\CreateTicketAction;
use App\Domain\Ticket\Actions\ResolveTicketAction;
use App\Domain\Ticket\DTOs\CreateTicketData;
use App\Domain\Ticket\Enums\TicketPriority;
use App\Domain\Ticket\Enums\TicketStatus;
use App\Domain\Ticket\Events\TicketAssigned;
use App\Domain\Ticket\Events\TicketCreated;
use App\Domain\Ticket\Events\TicketMessageAdded;
use App\Domain\Ticket\Events\TicketStatusChanged;
use App\Domain\Ticket\Listeners\SendTicketNotificationListener;
use App\Domain\Ticket\Mail\TicketNotificationMail;
use App\Domain\Ticket\Models\Category;
use App\Domain\Ticket\Models\Ticket;
use App\Domain\Ticket\Models\TicketMessage;
use App\Infrastructure\Notifications\EmailNotificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class TicketEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_notification_mailable_renders_html_and_text_templates(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
        ]);

        $ticket = Ticket::factory()->create([
            'customer_id' => $user->id,
            'subject' => 'Cannot access billing invoices',
            'priority' => TicketPriority::High,
            'status' => TicketStatus::Open,
        ]);

        $mailable = new TicketNotificationMail(
            recipient: $user,
            subjectTitle: "Ticket #{$ticket->id} Created",
            contentMessage: "Your ticket '{$ticket->subject}' has been received by support.",
            context: ['ticket_id' => $ticket->id],
            ticket: $ticket,
        );

        $mailable->assertHasSubject("Ticket #{$ticket->id} Created");
        $mailable->assertHasTo('alice@example.com');
        $mailable->assertSeeInHtml('Hello Alice');
        $mailable->assertSeeInHtml('Cannot access billing invoices');
        $mailable->assertSeeInHtml('View in Help Desk');
        $mailable->assertSeeInText('Hello Alice');
        $mailable->assertSeeInText('Cannot access billing invoices');
    }

    public function test_email_notification_sender_dispatches_mailable(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'bob@example.com']);
        $ticket = Ticket::factory()->create(['customer_id' => $user->id]);

        $sender = new EmailNotificationSender;
        $result = $sender->send(
            recipient: $user,
            title: 'Test Notification',
            content: 'This is a test notification message.',
            context: ['ticket' => $ticket, 'ticket_id' => $ticket->id],
        );

        $this->assertTrue($result->successful);
        $this->assertSame('bob@example.com', $result->recipientEmail);
        $this->assertSame('email', $result->channel);
        $this->assertNotEmpty($result->messageId);

        Mail::assertSent(TicketNotificationMail::class, function (TicketNotificationMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->subjectTitle === 'Test Notification';
        });
    }

    public function test_ticket_created_event_notifies_customer_and_admin_triage(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['email' => 'admin@support.com', 'role' => Role::Admin]);
        $customer = User::factory()->create(['email' => 'customer@client.com', 'role' => Role::Customer]);
        $category = Category::factory()->create();

        $createAction = new CreateTicketAction;
        $ticket = $createAction->execute(new CreateTicketData(
            customer_id: $customer->id,
            category_id: $category->id,
            subject: 'Outage on API endpoint',
            description: 'Experiencing 500 errors on payment api.',
            priority: TicketPriority::Urgent,
        ));

        // Trigger listener directly
        $listener = new SendTicketNotificationListener(new EmailNotificationSender);
        $listener->handleTicketCreated(new TicketCreated($ticket));

        // Assert customer received confirmation
        Mail::assertSent(TicketNotificationMail::class, function (TicketNotificationMail $mail) use ($customer) {
            return $mail->hasTo($customer->email) && str_contains($mail->subjectTitle, 'Created');
        });

        // Assert admin received triage notification
        Mail::assertSent(TicketNotificationMail::class, function (TicketNotificationMail $mail) use ($admin) {
            return $mail->hasTo($admin->email) && str_contains($mail->subjectTitle, 'New Ticket');
        });
    }

    public function test_ticket_status_changed_event_notifies_customer_and_assigned_agent(): void
    {
        Mail::fake();

        $customer = User::factory()->create(['email' => 'customer@client.com', 'role' => Role::Customer]);
        $agent = User::factory()->create(['email' => 'agent@support.com', 'role' => Role::Agent]);
        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $agent->id,
            'status' => TicketStatus::InProgess,
        ]);

        $resolveAction = new ResolveTicketAction;
        $resolveAction->execute($ticket, 'Resolved database deadlock.');

        $listener = new SendTicketNotificationListener(new EmailNotificationSender);
        $listener->handleTicketStatusChanged(new TicketStatusChanged(
            ticket: $ticket,
            previousStatus: TicketStatus::InProgess,
            newStatus: TicketStatus::Resolved,
        ));

        // Customer gets notified of status update
        Mail::assertSent(TicketNotificationMail::class, function (TicketNotificationMail $mail) use ($customer) {
            return $mail->hasTo($customer->email) && str_contains($mail->subjectTitle, 'Status Updated');
        });

        // Assigned agent gets notified of status change
        Mail::assertSent(TicketNotificationMail::class, function (TicketNotificationMail $mail) use ($agent) {
            return $mail->hasTo($agent->email) && str_contains($mail->subjectTitle, 'Status Changed');
        });
    }

    public function test_ticket_assigned_event_notifies_agent_and_customer(): void
    {
        Mail::fake();

        $customer = User::factory()->create(['email' => 'customer@client.com', 'role' => Role::Customer]);
        $agent = User::factory()->create(['email' => 'agent@support.com', 'role' => Role::Agent]);
        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => null,
            'subject' => 'SSL certificate error',
        ]);

        $assignAction = new AssignTicketAction;
        $assignAction->execute($ticket, $agent);

        $listener = new SendTicketNotificationListener(new EmailNotificationSender);
        $listener->handleTicketAssigned(new TicketAssigned(
            ticket: $ticket,
            agent: $agent,
        ));

        // Agent gets "Assigned to You"
        Mail::assertSent(TicketNotificationMail::class, function (TicketNotificationMail $mail) use ($agent) {
            return $mail->hasTo($agent->email) && str_contains($mail->subjectTitle, 'Assigned to You');
        });

        // Customer gets "Agent Assigned"
        Mail::assertSent(TicketNotificationMail::class, function (TicketNotificationMail $mail) use ($customer) {
            return $mail->hasTo($customer->email) && str_contains($mail->subjectTitle, 'Agent Assigned');
        });
    }

    public function test_internal_note_message_notifies_agent_and_admin_never_customer(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['email' => 'admin@support.com', 'role' => Role::Admin]);
        $agent = User::factory()->create(['email' => 'agent@support.com', 'role' => Role::Agent]);
        $customer = User::factory()->create(['email' => 'customer@client.com', 'role' => Role::Customer]);
        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $agent->id,
        ]);

        // Admin adds an internal note
        $messageAction = new AddTicketMessageAction;
        $message = $messageAction->execute(
            ticket: $ticket,
            user: $admin,
            message: 'Internal review: customer might be on legacy pricing tier.',
            isInternal: true,
        );

        $listener = new SendTicketNotificationListener(new EmailNotificationSender);
        $listener->handleTicketMessageAdded(new TicketMessageAdded($ticket, $message));

        // Agent received internal note alert
        Mail::assertSent(TicketNotificationMail::class, function (TicketNotificationMail $mail) use ($agent) {
            return $mail->hasTo($agent->email) && str_contains($mail->subjectTitle, 'Internal Note');
        });

        // Customer NEVER receives internal note email
        Mail::assertNotSent(TicketNotificationMail::class, function (TicketNotificationMail $mail) use ($customer) {
            return $mail->hasTo($customer->email);
        });
    }

    public function test_public_reply_notifies_opposite_party(): void
    {
        Mail::fake();

        $agent = User::factory()->create(['email' => 'agent@support.com', 'role' => Role::Agent]);
        $customer = User::factory()->create(['email' => 'customer@client.com', 'role' => Role::Customer]);
        $ticket = Ticket::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $agent->id,
        ]);

        $listener = new SendTicketNotificationListener(new EmailNotificationSender);

        // 1. Agent sends public message -> customer is notified
        $agentMessage = TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'is_internal' => false,
            'message' => 'We are working on your issue.',
        ]);
        $listener->handleTicketMessageAdded(new TicketMessageAdded($ticket, $agentMessage));

        Mail::assertSent(TicketNotificationMail::class, function (TicketNotificationMail $mail) use ($customer) {
            return $mail->hasTo($customer->email);
        });

        // 2. Customer replies -> agent is notified
        $customerMessage = TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'is_internal' => false,
            'message' => 'Thank you for the quick update!',
        ]);
        $listener->handleTicketMessageAdded(new TicketMessageAdded($ticket, $customerMessage));

        Mail::assertSent(TicketNotificationMail::class, function (TicketNotificationMail $mail) use ($agent) {
            return $mail->hasTo($agent->email);
        });
    }
}
