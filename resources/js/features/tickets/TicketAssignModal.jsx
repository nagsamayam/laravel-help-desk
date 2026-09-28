import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { useUiStore } from '@/stores/ui-store';
import { extractApiErrors } from '@/lib/utils';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Select } from '@/components/ui/Input';
import { Cpu, UserCheck, Users, AlertCircle } from 'lucide-react';

export function TicketAssignModal({ isOpen, onClose, ticket }) {
    const queryClient = useQueryClient();
    const { addToast } = useUiStore();

    const [mode, setMode] = useState('strategy');
    const [strategy, setStrategy] = useState('round_robin');
    const [agentId, setAgentId] = useState('');
    const [serverError, setServerError] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});

    // Fetch active agents and admins for assignment dropdown
    const { data: agentsData, isLoading: isLoadingAgents, isError: isAgentsError } = useQuery({
        queryKey: queryKeys.users.agents,
        queryFn: async () => {
            const res = await apiClient.get('/agents');
            return res.data?.data || res.data || [];
        },
        enabled: isOpen,
    });

    const agents = Array.isArray(agentsData) ? agentsData : [];

    useEffect(() => {
        if (isOpen && ticket) {
            setMode('strategy');
            setStrategy('round_robin');
            setAgentId(ticket.assigned_to ? String(ticket.assigned_to) : '');
            setServerError('');
            setFieldErrors({});
        }
    }, [isOpen, ticket]);

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

        if (mode === 'manual' && !agentId) {
            setFieldErrors({ agent_id: 'Please select an agent to assign this ticket to.' });
            return;
        }

        const payload = mode === 'strategy'
            ? { strategy }
            : { agent_id: parseInt(agentId, 10), assigned_to: parseInt(agentId, 10) };

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
                        <UserCheck className="w-3.5 h-3.5" /> Pick Agent
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
                            Select Agent *
                        </label>
                        {isLoadingAgents ? (
                            <div className="py-2 text-xs text-slate-500">Loading agents...</div>
                        ) : isAgentsError ? (
                            <div className="text-xs text-red-500">Failed to load agents list.</div>
                        ) : (
                            <Select
                                value={agentId}
                                onChange={(e) => {
                                    setAgentId(e.target.value);
                                    if (fieldErrors.agent_id || fieldErrors.assigned_to) {
                                        setFieldErrors((prev) => ({
                                            ...prev,
                                            agent_id: undefined,
                                            assigned_to: undefined,
                                        }));
                                    }
                                    if (serverError) setServerError('');
                                }}
                                error={fieldErrors.agent_id || fieldErrors.assigned_to}
                                required
                            >
                                <option value="">-- Choose an Agent --</option>
                                {agents.map((agent) => (
                                    <option key={agent.id} value={agent.id}>
                                        {agent.name || `${agent.first_name || ''} ${agent.last_name || ''}`.trim()} ({agent.role}) - {agent.email}
                                    </option>
                                ))}
                            </Select>
                        )}
                        <p className="text-[11px] text-slate-500 mt-1">
                            Directly assigns ticket responsibility to the chosen support agent.
                        </p>
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
