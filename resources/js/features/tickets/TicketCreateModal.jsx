import React, { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { useUiStore } from '@/stores/ui-store';
import { extractApiErrors } from '@/lib/utils';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Input, Textarea, Select } from '@/components/ui/Input';

export function TicketCreateModal({ isOpen, onClose }) {
    const queryClient = useQueryClient();
    const { addToast } = useUiStore();

    const [subject, setSubject] = useState('');
    const [description, setDescription] = useState('');
    const [priority, setPriority] = useState('MEDIUM');
    const [categoryId, setCategoryId] = useState('1');
    const [errors, setErrors] = useState({});
    const [serverError, setServerError] = useState('');

    const createMutation = useMutation({
        mutationFn: async (payload) => {
            const res = await apiClient.post('/tickets', payload);
            return res.data?.data || res.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: queryKeys.tickets.all });
            addToast({
                type: 'success',
                title: 'Ticket Created',
                message: `Ticket #${data.id} has been created successfully.`,
            });
            onClose();
            setSubject('');
            setDescription('');
            setPriority('MEDIUM');
            setErrors({});
            setServerError('');
        },
        onError: (err) => {
            const { message, errors: fieldErrors } = extractApiErrors(err);
            setServerError(message || 'Could not create ticket.');
            setErrors(fieldErrors || {});
            addToast({
                type: 'error',
                title: 'Creation Failed',
                message: message || 'Could not create ticket.',
            });
        },
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        setErrors({});
        setServerError('');

        const newErrors = {};
        if (!subject.trim()) {
            newErrors.subject = 'Subject is required';
        }

        if (!description.trim()) {
            newErrors.description = 'Description is required';
        }

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        createMutation.mutate({
            subject: subject.trim(),
            description: description.trim(),
            priority,
            category_id: parseInt(categoryId, 10),
        });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title="Create Support Ticket"
            description="Submit a new incident or service request with automatic idempotency protection."
        >
            <form onSubmit={handleSubmit} className="space-y-4">
                {serverError && (
                    <div className="p-3 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 rounded-lg">
                        {serverError}
                    </div>
                )}

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Subject *
                    </label>
                    <Input
                        placeholder="e.g. Cannot access database billing portal"
                        value={subject}
                        onChange={(e) => {
                            setSubject(e.target.value);
                            if (errors.subject) setErrors((prev) => ({ ...prev, subject: undefined }));
                            if (serverError) setServerError('');
                        }}
                        error={errors.subject}
                        required
                    />
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Priority
                        </label>
                        <Select
                            value={priority}
                            onChange={(e) => {
                                setPriority(e.target.value);
                                if (errors.priority) setErrors((prev) => ({ ...prev, priority: undefined }));
                            }}
                            error={errors.priority}
                        >
                            <option value="LOW">Low</option>
                            <option value="MEDIUM">Medium</option>
                            <option value="HIGH">High</option>
                            <option value="URGENT">Urgent</option>
                        </Select>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Category
                        </label>
                        <Select
                            value={categoryId}
                            onChange={(e) => {
                                setCategoryId(e.target.value);
                                if (errors.category_id) setErrors((prev) => ({ ...prev, category_id: undefined }));
                            }}
                            error={errors.category_id}
                        >
                            <option value="1">Technical Support</option>
                            <option value="2">Billing & Account</option>
                            <option value="3">Feature Request</option>
                            <option value="4">Security & Incident</option>
                        </Select>
                    </div>
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Detailed Description *
                    </label>
                    <Textarea
                        rows={4}
                        placeholder="Provide relevant details, error logs, or steps to reproduce..."
                        value={description}
                        onChange={(e) => {
                            setDescription(e.target.value);
                            if (errors.description) setErrors((prev) => ({ ...prev, description: undefined }));
                            if (serverError) setServerError('');
                        }}
                        error={errors.description}
                        required
                    />
                </div>

                <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <Button type="button" variant="outline" onClick={onClose} disabled={createMutation.isPending}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={createMutation.isPending}>
                        Submit Ticket
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
