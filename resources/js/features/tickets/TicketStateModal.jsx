import React, { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { useUiStore } from '@/stores/ui-store';
import { extractApiErrors } from '@/lib/utils';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Select, Textarea } from '@/components/ui/Input';

export function TicketStateModal({ isOpen, onClose, ticket }) {
    const queryClient = useQueryClient();
    const { addToast } = useUiStore();

    const [actionType, setActionType] = useState('transition');
    const [targetStatus, setTargetStatus] = useState('IN_PROGRESS');
    const [reason, setReason] = useState('');
    const [serverError, setServerError] = useState('');
    const [fieldErrors, setFieldErrors] = useState({});

    const transitionMutation = useMutation({
        mutationFn: async ({ ticketId, type, status, reasonText }) => {
            let endpoint = `/tickets/${ticketId}/transition`;
            let payload = { status, reason: reasonText };

            if (type === 'resolve') {
                endpoint = `/tickets/${ticketId}/resolve`;
                payload = { resolution_notes: reasonText, reason: reasonText };
            } else if (type === 'close') {
                endpoint = `/tickets/${ticketId}/close`;
                payload = { reason: reasonText };
            } else if (type === 'reopen') {
                endpoint = `/tickets/${ticketId}/reopen`;
                payload = { reason: reasonText };
            }

            const res = await apiClient.post(endpoint, payload);
            return res.data?.data || res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: queryKeys.tickets.all });
            addToast({
                type: 'success',
                title: 'State Transition Applied',
                message: `Ticket #${ticket.id} updated to ${data.status || targetStatus}.`,
            });
            onClose();
            setReason('');
            setServerError('');
            setFieldErrors({});
        },
        onError: (err) => {
            const { message, errors } = extractApiErrors(err);
            setServerError(message || 'Invalid state transition.');
            setFieldErrors(errors || {});
            addToast({
                type: 'error',
                title: 'Transition Failed',
                message: message || 'Invalid state transition.',
            });
        },
    });

    if (!ticket) return null;

    const handleSubmit = (e) => {
        e.preventDefault();
        setServerError('');
        setFieldErrors({});
        transitionMutation.mutate({
            ticketId: ticket.id,
            type: actionType,
            status: targetStatus,
            reasonText: reason,
        });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={`Update Ticket State #${ticket.id}`}
            description="Execute domain state pattern transitions with audit tracking."
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                {serverError && (
                    <div className="p-3 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 rounded-lg">
                        {serverError}
                    </div>
                )}

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Transition Action
                    </label>
                    <Select
                        value={actionType}
                        onChange={(e) => {
                            setActionType(e.target.value);
                            if (e.target.value === 'resolve') setTargetStatus('RESOLVED');
                            if (e.target.value === 'close') setTargetStatus('CLOSED');
                            if (e.target.value === 'reopen') setTargetStatus('OPEN');
                            if (serverError) setServerError('');
                        }}
                    >
                        <option value="transition">Custom State Transition</option>
                        <option value="resolve">Resolve Ticket (Command)</option>
                        <option value="close">Close Ticket (Command)</option>
                        <option value="reopen">Reopen Ticket (Command)</option>
                    </Select>
                </div>

                {actionType === 'transition' && (
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Target Status
                        </label>
                        <Select
                            value={targetStatus}
                            onChange={(e) => {
                                setTargetStatus(e.target.value);
                                if (fieldErrors.status) setFieldErrors((prev) => ({ ...prev, status: undefined }));
                            }}
                            error={fieldErrors.status}
                        >
                            <option value="OPEN">Open</option>
                            <option value="IN_PROGRESS">In Progress</option>
                            <option value="WAITING_FOR_CUSTOMER">Waiting for Customer</option>
                            <option value="RESOLVED">Resolved</option>
                            <option value="CLOSED">Closed</option>
                        </Select>
                    </div>
                )}

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Reason / Resolution Notes
                    </label>
                    <Textarea
                        rows={3}
                        placeholder="Explain the reason for this state change (recorded in status history and audit trail)..."
                        value={reason}
                        onChange={(e) => {
                            setReason(e.target.value);
                            if (fieldErrors.reason || fieldErrors.resolution_notes) {
                                setFieldErrors((prev) => ({ ...prev, reason: undefined, resolution_notes: undefined }));
                            }
                        }}
                        error={fieldErrors.reason || fieldErrors.resolution_notes}
                    />
                </div>

                <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <Button type="button" variant="outline" onClick={onClose} disabled={transitionMutation.isPending}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={transitionMutation.isPending}>
                        Apply Transition
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
