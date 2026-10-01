import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { useUiStore } from '@/stores/ui-store';
import { extractApiErrors } from '@/lib/utils';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { GitFork, CheckCircle, ShieldAlert, Cpu } from 'lucide-react';
import { PriorityBadge } from '@/components/ui/Badge';

export function TicketRoutingModal({ isOpen, onClose, ticket }) {
    const queryClient = useQueryClient();
    const { addToast } = useUiStore();
    const [result, setResult] = useState(null);
    const [serverError, setServerError] = useState('');

    const routeMutation = useMutation({
        mutationFn: async ({ ticketId, persist }) => {
            const res = await apiClient.post(`/tickets/${ticketId}/route`, { persist });
            return res.data?.data || res.data;
        },
        onSuccess: (data) => {
            setResult(data);
            setServerError('');
            queryClient.invalidateQueries({ queryKey: queryKeys.tickets.all });
            addToast({
                type: 'success',
                title: 'Chain of Responsibility Evaluated',
                message: `Matched rule: ${data.rule_name || 'Processed'}`,
            });
        },
        onError: (err) => {
            const { message } = extractApiErrors(err);
            setServerError(message || 'Could not route ticket.');
            addToast({
                type: 'error',
                title: 'Routing Evaluation Failed',
                message: message || 'Could not route ticket.',
            });
        },
    });

    if (!ticket) return null;

    const handleRunRouting = (persist) => {
        setServerError('');
        routeMutation.mutate({ ticketId: ticket.id, persist });
    };


    return (
        <Modal
            isOpen={isOpen}
            onClose={() => {
                setResult(null);
                setServerError('');
                onClose();
            }}
            title={`Chain of Responsibility Routing #${ticket.id}`}
            description="Evaluate VIP, Urgent Priority, Category, and Default routing handlers through the responsibility pipeline."
            maxWidth="max-w-xl"
        >
            <div className="space-y-4">
                {serverError && (
                    <div className="p-3 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 rounded-lg">
                        {serverError}
                    </div>
                )}

                <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 text-xs space-y-2">
                    <div className="font-semibold text-slate-800 dark:text-slate-200">Current Ticket Properties:</div>
                    <div className="grid grid-cols-2 gap-2 text-slate-600 dark:text-slate-400">
                        <div>Priority: <PriorityBadge priority={ticket.priority} /></div>
                        <div>Category: <span className="font-medium text-slate-800 dark:text-slate-200">{ticket.category?.name || 'General'}</span></div>
                        <div>Customer VIP: <span className="font-medium text-slate-800 dark:text-slate-200">{ticket.customer?.is_vip ? 'Yes (VIP)' : 'Standard'}</span></div>
                        <div>Assigned Agent: <span className="font-medium text-slate-800 dark:text-slate-200">{ticket.assigned_to_user?.name || ticket.assigned_agent?.name || 'Unassigned'}</span></div>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleRunRouting(false)}
                        loading={routeMutation.isPending}
                        className="flex-1 gap-2"
                    >
                        <Cpu className="w-4 h-4" /> Simulate Pipeline (Dry Run)
                    </Button>
                    <Button
                        type="button"
                        variant="default"
                        onClick={() => handleRunRouting(true)}
                        loading={routeMutation.isPending}
                        className="flex-1 gap-2"
                    >
                        <GitFork className="w-4 h-4" /> Apply & Persist Routing
                    </Button>
                </div>

                {result && (
                    <div className="p-4 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 animate-in fade-in space-y-3">
                        <div className="flex items-center gap-2 text-indigo-900 dark:text-indigo-200 font-semibold text-sm">
                            <CheckCircle className="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                            Pipeline Decision Outcome
                        </div>

                        <div className="text-xs space-y-1 text-slate-700 dark:text-slate-300">
                            <div>
                                <strong className="text-slate-900 dark:text-slate-100">
                                    Handler Matched:
                                </strong>
                                <span className="font-mono bg-white dark:bg-slate-900 px-1.5 py-0.5 rounded border border-indigo-200 dark:border-indigo-800">
                                    {result.matched_rule || result.handler}
                                </span>
                            </div>
                            <div><strong className="text-slate-900 dark:text-slate-100">Assigned Department / Agent:</strong> {result.assigned_to?.name || `Agent #${result.assigned_to}` || 'N/A'}</div>
                            <div><strong className="text-slate-900 dark:text-slate-100">Priority Assigned:</strong> <PriorityBadge priority={result.priority || ticket.priority} /></div>
                            <div><strong className="text-slate-900 dark:text-slate-100">Routing Reason:</strong> {result.reason || result.message}</div>
                        </div>
                    </div>
                )}
            </div>
        </Modal>
    );
}
