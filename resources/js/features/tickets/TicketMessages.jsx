import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { useAuthStore } from '@/stores/auth-store';
import { useUiStore } from '@/stores/ui-store';
import { extractApiErrors } from '@/lib/utils';
import { Button } from '@/components/ui/Button';
import { Textarea } from '@/components/ui/Input';
import { LoadingSpinner, EmptyState } from '@/components/ui/LoadingSpinner';
import { formatRelativeTime } from '@/lib/utils';
import { Send, Lock, MessageSquare, User, ShieldAlert } from 'lucide-react';

export function TicketMessages({ ticketId }) {
    const { user, isAgent } = useAuthStore();
    const { addToast } = useUiStore();
    const queryClient = useQueryClient();

    const [body, setBody] = useState('');
    const [isInternal, setIsInternal] = useState(false);
    const [serverError, setServerError] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});

    const { data: messages, isLoading, isError, error } = useQuery({
        queryKey: queryKeys.tickets.messages(ticketId),
        queryFn: async () => {
            const res = await apiClient.get(`/tickets/${ticketId}/messages`);
            return res.data?.data || res.data;
        },
    });

    const addMessageMutation = useMutation({
        mutationFn: async (payload) => {
            const res = await apiClient.post(`/tickets/${ticketId}/messages`, payload);
            return res.data?.data || res.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: queryKeys.tickets.messages(ticketId) });
            queryClient.invalidateQueries({ queryKey: queryKeys.tickets.detail(ticketId) });
            queryClient.invalidateQueries({ queryKey: queryKeys.tickets.history(ticketId) });
            setBody('');
            setIsInternal(false);
            setServerError('');
            setFieldErrors({});
            addToast({
                type: 'success',
                title: 'Message Sent',
                message: isInternal ? 'Internal note added.' : 'Reply sent to customer.',
            });
        },
        onError: (err) => {
            const { message, errors } = extractApiErrors(err);
            setServerError(message || 'Failed to post reply.');
            setFieldErrors(errors || {});
            addToast({
                type: 'error',
                title: 'Could not send message',
                message: message || 'Failed to post reply.',
            });
        },
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        setServerError('');
        setFieldErrors({});
        if (!body.trim()) {
            setFieldErrors({ body: 'Message body cannot be empty.' });
            return;
        }

        addMessageMutation.mutate({
            message: body.trim(),
            body: body.trim(),
            is_internal: isAgent() ? isInternal : false,
        });
    };

    if (isLoading) {
        return <LoadingSpinner text="Loading conversation..." size="sm" />;
    }

    if (isError) {
        return (
            <div className="p-4 text-xs text-red-600 bg-red-50 dark:bg-red-950/30 rounded-lg">
                Failed to load messages: {error?.message}
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Messages Feed */}
            <div className="space-y-4">
                {!messages || messages.length === 0 ? (
                    <EmptyState
                        icon={MessageSquare}
                        title="No messages yet"
                        description="Start the conversation by posting a reply below."
                    />
                ) : (
                    messages.map((msg) => {
                        const isOwn = msg.user_id === user?.id;
                        const isInternalNote = msg.is_internal;

                        return (
                            <div
                                key={msg.id}
                                className={`flex gap-3 p-4 rounded-xl border transition-all ${
                                    isInternalNote
                                        ? 'bg-amber-50/70 dark:bg-amber-950/30 border-amber-200 dark:border-amber-900/60'
                                        : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800'
                                }`}
                            >
                                <div className="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center shrink-0 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    {(msg.user?.name || msg.author_name || 'U')[0]}
                                </div>

                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center justify-between mb-1">
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-semibold text-slate-900 dark:text-slate-100">
                                                {msg.user?.name || msg.author_name || 'Support User'}
                                            </span>
                                            <span className="text-[10px] text-slate-400 capitalize bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">
                                                {msg.user?.role || 'User'}
                                            </span>
                                            {isInternalNote && (
                                                <span className="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-900/50 px-2 py-0.5 rounded-full border border-amber-300 dark:border-amber-800">
                                                    <Lock className="w-2.5 h-2.5" /> Internal Note
                                                </span>
                                            )}
                                        </div>
                                        <span className="text-[11px] text-slate-400">
                                            {formatRelativeTime(msg.created_at)}
                                        </span>
                                    </div>

                                    <div className="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed">
                                        {msg.message || msg.body}
                                    </div>
                                </div>
                            </div>
                        );
                    })
                )}
            </div>

            {/* Message Reply Box */}
            <form onSubmit={handleSubmit} className="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3">
                {serverError && (
                    <div className="p-3 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 rounded-lg">
                        {serverError}
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <span className="text-xs font-semibold text-slate-700 dark:text-slate-300">
                        {isInternal ? 'Add Internal Note (Staff Only)' : 'Reply to Customer'}
                    </span>

                    {isAgent() && (
                        <button
                            type="button"
                            onClick={() => setIsInternal(!isInternal)}
                            className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium transition-colors cursor-pointer ${
                                isInternal
                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-800'
                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 hover:bg-slate-200'
                            }`}
                        >
                            <Lock className="w-3 h-3" />
                            <span>{isInternal ? 'Internal Note Active' : 'Make Internal Note'}</span>
                        </button>
                    )}
                </div>

                <Textarea
                    rows={3}
                    placeholder={
                        isInternal
                            ? 'Write internal notes visible only to agents and admins...'
                            : 'Type your message or solution here...'
                    }
                    value={body}
                    onChange={(e) => {
                        setBody(e.target.value);
                        if (fieldErrors.body) setFieldErrors((prev) => ({ ...prev, body: undefined }));
                        if (serverError) setServerError('');
                    }}
                    error={fieldErrors.body}
                    required
                />

                <div className="flex justify-end">
                    <Button
                        type="submit"
                        loading={addMessageMutation.isPending}
                        variant={isInternal ? 'amber' : 'default'}
                        className="gap-2"
                    >
                        <Send className="w-4 h-4" />
                        <span>{isInternal ? 'Save Internal Note' : 'Send Message'}</span>
                    </Button>
                </div>
            </form>
        </div>
    );
}
