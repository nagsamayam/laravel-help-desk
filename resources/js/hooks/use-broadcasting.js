import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/auth-store';
import { useUiStore } from '@/stores/ui-store';
import { useBroadcastStore } from '@/stores/broadcast-store';
import { getEcho, disconnectEcho } from '@/lib/echo';
import { queryKeys } from '@/lib/query-keys';

/**
 * Global hook to manage Laravel Reverb WebSocket connection, channel subscriptions,
 * and automatic React Query cache invalidation.
 */
export function useBroadcasting() {
    const { user, token, isAuthenticated, isAgent } = useAuthStore();
    const { addToast } = useUiStore();
    const { addBroadcastMessage } = useBroadcastStore();
    const queryClient = useQueryClient();
    const [connectionStatus, setConnectionStatus] = useState('disconnected'); // 'connected' | 'connecting' | 'disconnected' | 'unavailable'

    useEffect(() => {
        if (!isAuthenticated || !token || !user) {
            disconnectEcho();
            setConnectionStatus('disconnected');
            return;
        }

        const echo = getEcho();
        if (!echo) {
            setConnectionStatus('unavailable');
            return;
        }

        // Bind connection status events from Pusher connector
        const pusher = echo.connector?.pusher;
        if (pusher && pusher.connection) {
            setConnectionStatus(pusher.connection.state || 'connecting');

            const handleStateChange = (states) => {
                setConnectionStatus(states.current);
            };

            pusher.connection.bind('state_change', handleStateChange);
            pusher.connection.bind('connected', () => setConnectionStatus('connected'));
            pusher.connection.bind('disconnected', () => setConnectionStatus('disconnected'));
            pusher.connection.bind('unavailable', () => setConnectionStatus('unavailable'));
        }

        // 1. Subscribe to personal user private channel
        const userChannelName = `users.${user.id}`;
        const userChannel = echo.private(userChannelName);

        userChannel
            .listen('.ticket.created', (event) => {
                queryClient.invalidateQueries({ queryKey: ['tickets'] });
                addBroadcastMessage({
                    type: 'ticket.created',
                    channel: `private-${userChannelName}`,
                    title: `Ticket #${event.ticket.id} Created`,
                    description: `${event.ticket.subject} (${event.ticket.priority})`,
                    ticketId: event.ticket.id,
                    payload: event,
                });
            })
            .listen('.ticket.status.changed', (event) => {
                queryClient.invalidateQueries({ queryKey: ['tickets'] });
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.detail(event.ticket_id) });
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.history(event.ticket_id) });

                addBroadcastMessage({
                    type: 'ticket.status.changed',
                    channel: `private-${userChannelName}`,
                    title: `Status Changed on Ticket #${event.ticket_id}`,
                    description: `Status transitioned to "${event.new_status}".`,
                    ticketId: event.ticket_id,
                    payload: event,
                });

                addToast({
                    type: 'info',
                    title: 'Ticket Status Updated',
                    message: `Ticket #${event.ticket_id} status changed to ${event.new_status}.`,
                });
            })
            .listen('.ticket.assigned', (event) => {
                queryClient.invalidateQueries({ queryKey: ['tickets'] });
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.detail(event.ticket_id) });
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.history(event.ticket_id) });

                addBroadcastMessage({
                    type: 'ticket.assigned',
                    channel: `private-${userChannelName}`,
                    title: `Ticket #${event.ticket_id} Assignment`,
                    description: event.assigned_to === user.id ? 'Assigned to you.' : `Assigned to agent #${event.assigned_to}.`,
                    ticketId: event.ticket_id,
                    payload: event,
                });

                if (event.assigned_to === user.id) {
                    addToast({
                        type: 'info',
                        title: 'Ticket Assigned',
                        message: `Ticket #${event.ticket_id} has been assigned to you.`,
                    });
                }
            });

        // 2. Subscribe to agent feed channel for staff members
        let agentChannel = null;
        if (isAgent()) {
            agentChannel = echo.private('agent.feed');

            agentChannel
                .listen('.ticket.created', (event) => {
                    queryClient.invalidateQueries({ queryKey: ['tickets'] });

                    addBroadcastMessage({
                        type: 'ticket.created',
                        channel: 'private-agent.feed',
                        title: `New Ticket #${event.ticket.id}`,
                        description: `${event.ticket.subject} (${event.ticket.priority})`,
                        ticketId: event.ticket.id,
                        payload: event,
                    });

                    addToast({
                        type: 'info',
                        title: 'New Support Ticket',
                        message: `#${event.ticket.id}: ${event.ticket.subject} (${event.ticket.priority})`,
                    });
                })
                .listen('.ticket.status.changed', (event) => {
                    queryClient.invalidateQueries({ queryKey: ['tickets'] });
                    queryClient.invalidateQueries({ queryKey: queryKeys.tickets.detail(event.ticket_id) });

                    addBroadcastMessage({
                        type: 'ticket.status.changed',
                        channel: 'private-agent.feed',
                        title: `Ticket #${event.ticket_id} Status: ${event.new_status}`,
                        description: `Status changed from ${event.old_status} to ${event.new_status}`,
                        ticketId: event.ticket_id,
                        payload: event,
                    });
                })
                .listen('.ticket.assigned', (event) => {
                    queryClient.invalidateQueries({ queryKey: ['tickets'] });
                    queryClient.invalidateQueries({ queryKey: queryKeys.tickets.detail(event.ticket_id) });

                    addBroadcastMessage({
                        type: 'ticket.assigned',
                        channel: 'private-agent.feed',
                        title: `Ticket #${event.ticket_id} Assigned`,
                        description: `Assigned to Agent #${event.assigned_to}`,
                        ticketId: event.ticket_id,
                        payload: event,
                    });
                })
                .listen('.ticket.message.added', (event) => {
                    queryClient.invalidateQueries({ queryKey: ['tickets'] });
                    if (event.ticket_id) {
                        queryClient.invalidateQueries({ queryKey: queryKeys.tickets.messages(event.ticket_id) });
                        queryClient.invalidateQueries({ queryKey: queryKeys.tickets.detail(event.ticket_id) });
                    }

                    addBroadcastMessage({
                        type: 'ticket.message.added',
                        channel: 'private-agent.feed',
                        title: `New Reply on Ticket #${event.ticket_id}`,
                        description: event.message?.message
                            ? (event.message.message.length > 80 ? event.message.message.substring(0, 80) + '...' : event.message.message)
                            : 'New message received',
                        ticketId: event.ticket_id,
                        payload: event,
                    });
                });
        }

        return () => {
            if (echo) {
                echo.leave(userChannelName);
                if (isAgent()) {
                    echo.leave('agent.feed');
                }
            }
        };
    }, [isAuthenticated, token, user?.id, user?.role, queryClient, addToast, addBroadcastMessage, isAgent]);

    return { connectionStatus };
}
