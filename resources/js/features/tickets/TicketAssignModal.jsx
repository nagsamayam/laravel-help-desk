import React, { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { useUiStore } from '@/stores/ui-store';
import { extractApiErrors } from '@/lib/utils';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Select, Input } from '@/components/ui/Input';
import { Cpu, UserCheck } from 'lucide-react';

export function TicketAssignModal({ isOpen, onClose, ticket }) {
    const queryClient = useQueryClient();
    const { addToast } = useUiStore();

    const [mode, setMode] = useState('strategy');
    const [strategy, setStrategy] = useState('round_robin');
    const [agentId, setAgentId] = useState('');
    const [serverError, setServerError] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});

    const assignMutation = useMutation({
        mutationFn: async ({ ticketId, payload }) => {
            const res = await apiClient.post(`/tickets/${ticketId}/assign`, payload);
            return res.data?.data || res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: queryKeys.tickets.all });
            const assignedAgent = data.assigned_agent?.name || data.assigned_to_user?.name || `Agent #${data.assigned_to}`;
            addToast({
                type: 'success',
                title: 'Assignment Complete',
                message: `Ticket #${ticket.id} assigned to ${assignedAgent}.`,
            });
            onClose();
            setServerError('');
            setFieldErrors({});
        },
        onError: (err) => {
            const { message, errors } = extractApiErrors(err);
            setServerError(message || 'Could not assign ticket.');
            setFieldErrors(errors || {});
            addToast({
                type: 'error',
                title: 'Assignment Failed',
                message: message || 'Could not assign ticket.',
            });
        },
    });

    if (!ticket) return null;

    const handleSubmit = (e) => {
        e.preventDefault();
        setServerError('');
        setFieldErrors({});
        const payload = mode === 'strategy' ? { strategy } : { assigned_to: parseInt(agentId, 10) };
        assignMutation.mutate({ ticketId: ticket.id, payload });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={`Assign Ticket #${ticket.id}`}
            description="Use Strategy Pattern algorithm (Round Robin, Least Busy, Skill Based) or select an agent directly."
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                {serverError && (
                    <div className="p-3 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 rounded-lg">
                        {serverError}
                    </div>
                )}

                <div className="grid grid-cols-2 gap-2 p-1 bg-slate-100 dark:bg-slate-800 rounded-lg">
                    <button
                        type="button"
                        onClick={() => {
                            setMode('strategy');
                            setServerError('');
                            setFieldErrors({});
                        }}
                        className={`flex items-center justify-center gap-1.5 py-1.5 text-xs font-semibold rounded-md transition-colors cursor-pointer ${
                            mode === 'strategy'
                                ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400'
                        }`}
                    >
                        <Cpu className="w-3.5 h-3.5" /> Strategy Engine
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setMode('manual');
                            setServerError('');
                            setFieldErrors({});
                        }}
                        className={`flex items-center justify-center gap-1.5 py-1.5 text-xs font-semibold rounded-md transition-colors cursor-pointer ${
                            mode === 'manual'
                                ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400'
                        }`}
                    >
                        <UserCheck className="w-3.5 h-3.5" /> Direct Agent ID
                    </button>
                </div>

                {mode === 'strategy' ? (
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Assignment Strategy
                        </label>
                        <Select
                            value={strategy}
                            onChange={(e) => {
                                setStrategy(e.target.value);
                                if (fieldErrors.strategy) setFieldErrors((prev) => ({ ...prev, strategy: undefined }));
                            }}
                            error={fieldErrors.strategy}
                        >
                            <option value="round_robin">Round Robin (Even Distribution)</option>
                            <option value="least_busy">Least Busy (Workload Balancing)</option>
                            <option value="skill_based">Skill Based (Category / Skill Match)</option>
                        </Select>
                        <p className="text-[11px] text-slate-500 mt-1">
                            Evaluates active agent availability and balances workload automatically.
                        </p>
                    </div>
                ) : (
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Agent User ID *
                        </label>
                        <Input
                            type="number"
                            placeholder="e.g. 2"
                            value={agentId}
                            onChange={(e) => {
                                setAgentId(e.target.value);
                                if (fieldErrors.assigned_to) setFieldErrors((prev) => ({ ...prev, assigned_to: undefined }));
                            }}
                            error={fieldErrors.assigned_to}
                            required
                        />
                    </div>
                )}

                <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <Button type="button" variant="outline" onClick={onClose} disabled={assignMutation.isPending}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={assignMutation.isPending}>
                        Assign Ticket
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
