import { useEffect, useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/auth-store';
import { useUiStore } from '@/stores/ui-store';
import { useBroadcastStore } from '@/stores/broadcast-store';
import { getEcho } from '@/lib/echo';
import { queryKeys } from '@/lib/query-keys';

/**
 * Hook to manage real-time presence channel (agent collision detection) and live updates
 * on a specific ticket conversation.
 */
export function useTicketRealtime(ticketId) {
    const { user, isAuthenticated, isAgent } = useAuthStore();
    const { addToast } = useUiStore();
    const { addBroadcastMessage } = useBroadcastStore();
    const queryClient = useQueryClient();
    const [activeViewers, setActiveViewers] = useState([]);

    useEffect(() => {
        if (!isAuthenticated || !ticketId) {
            setActiveViewers([]);
            return;
        }

        const echo = getEcho();
        if (!echo) return;

        const presenceChannelName = `tickets.${ticketId}`;
        const presenceChannel = echo.join(presenceChannelName);

        // Presence channel collision detection
        presenceChannel
            .here((users) => {
                setActiveViewers(users || []);
            })
            .joining((joiningUser) => {
                setActiveViewers((prev) => {
                    if (prev.some((u) => u.id === joiningUser.id)) {
                        return prev;
                    }
                    return [...prev, joiningUser];
                });
            })
            .leaving((leavingUser) => {
                setActiveViewers((prev) => prev.filter((u) => u.id !== leavingUser.id));
            })
            .listen('.ticket.message.added', (event) => {
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.messages(ticketId) });
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.detail(ticketId) });
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.history(ticketId) });

                // If sent by another user, notify and record
                if (event.message?.user_id && event.message.user_id !== user?.id) {
                    addBroadcastMessage({
                        type: 'ticket.message.added',
                        channel: `presence-${presenceChannelName}`,
                        title: `New Reply on Ticket #${ticketId}`,
                        description: `${event.message.user?.name || 'Someone'}: ${event.message.message?.substring(0, 80) || ''}`,
                        ticketId: ticketId,
                        payload: event,
                    });

                    addToast({
                        type: 'info',
                        title: 'New Reply Received',
                        message: `${event.message.user?.name || 'Someone'} replied on Ticket #${ticketId}.`,
                    });
                }
            })
            .listen('.ticket.status.changed', (event) => {
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.detail(ticketId) });
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.history(ticketId) });
                queryClient.invalidateQueries({ queryKey: ['tickets'] });
            })
            .listen('.ticket.assigned', (event) => {
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.detail(ticketId) });
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.history(ticketId) });
                queryClient.invalidateQueries({ queryKey: ['tickets'] });
            });

        // For agents/admins, also listen on internal ticket channel for internal notes
        let internalChannel = null;
        const internalChannelName = `tickets.${ticketId}.internal`;
        if (isAgent()) {
            internalChannel = echo.private(internalChannelName);
            internalChannel.listen('.ticket.message.added', (event) => {
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.messages(ticketId) });
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.detail(ticketId) });
                queryClient.invalidateQueries({ queryKey: queryKeys.tickets.history(ticketId) });
            });
        }

        return () => {
            if (echo) {
                echo.leave(presenceChannelName);
                if (isAgent()) {
                    echo.leave(internalChannelName);
                }
            }
            setActiveViewers([]);
        };
    }, [ticketId, isAuthenticated, user?.id, isAgent, queryClient, addToast, addBroadcastMessage]);

    return {
        activeViewers,
        otherViewers: activeViewers.filter((v) => v.id !== user?.id),
    };
}
