import React, { useState, useRef } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { useUiStore } from '@/stores/ui-store';
import { extractApiErrors } from '@/lib/utils';
import {
    uploadFileChunked,
    validateAttachmentFiles,
    formatBytes,
    MAX_FILE_SIZE,
    MAX_TOTAL_SIZE,
    MAX_FILES_COUNT,
} from '@/lib/chunk-uploader';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Input, Textarea, Select } from '@/components/ui/Input';
import {
    Paperclip,
    UploadCloud,
    FileText,
    Image as ImageIcon,
    X,
    AlertCircle,
    CheckCircle2,
    RefreshCw,
} from 'lucide-react';

export function TicketCreateModal({ isOpen, onClose }) {
    const queryClient = useQueryClient();
    const { addToast } = useUiStore();
    const fileInputRef = useRef(null);

    const [subject, setSubject] = useState('');
    const [description, setDescription] = useState('');
    const [priority, setPriority] = useState('MEDIUM');
    const [categoryId, setCategoryId] = useState('1');
    const [errors, setErrors] = useState({});
    const [serverError, setServerError] = useState('');
    const [attachmentError, setAttachmentError] = useState('');

    // Array of upload items: { id: temp_id, file, attachmentId, status, progress, error, isImage, isPdf }
    const [attachments, setAttachments] = useState([]);
    const [isDragging, setIsDragging] = useState(false);

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
            handleClose();
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

    const handleClose = () => {
        onClose();
        setSubject('');
        setDescription('');
        setPriority('MEDIUM');
        setErrors({});
        setServerError('');
        setAttachmentError('');
        setAttachments([]);
    };

    const totalAttachedSize = attachments.reduce((acc, item) => acc + (item.file?.size || 0), 0);
    const hasUploadingFiles = attachments.some((item) => item.status === 'uploading' || item.status === 'initializing');

    const startFileUpload = async (uploadItem) => {
        setAttachments((prev) =>
            prev.map((item) =>
                item.id === uploadItem.id ? { ...item, status: 'uploading', progress: 0, error: null } : item
            )
        );

        try {
            const attachment = await uploadFileChunked(uploadItem.file, {
                onProgress: ({ percent, currentChunk, totalChunks }) => {
                    setAttachments((prev) =>
                        prev.map((item) =>
                            item.id === uploadItem.id
                                ? { ...item, progress: percent, chunkInfo: `${currentChunk}/${totalChunks}` }
                                : item
                        )
                    );
                },
            });

            setAttachments((prev) =>
                prev.map((item) =>
                    item.id === uploadItem.id
                        ? { ...item, status: 'completed', progress: 100, attachmentId: attachment.id }
                        : item
                )
            );
        } catch (err) {
            setAttachments((prev) =>
                prev.map((item) =>
                    item.id === uploadItem.id
                        ? { ...item, status: 'error', error: err.message || 'Upload failed' }
                        : item
                )
            );
        }
    };

    const handleFilesAdded = (filesList) => {
        setAttachmentError('');
        const newFiles = Array.from(filesList);
        if (newFiles.length === 0) return;

        const validation = validateAttachmentFiles(attachments, newFiles);
        if (!validation.valid) {
            setAttachmentError(validation.error);
            return;
        }

        const newItems = newFiles.map((file) => ({
            id: `${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
            file,
            attachmentId: null,
            status: 'initializing',
            progress: 0,
            error: null,
            isPdf: file.type === 'application/pdf' || file.name.endsWith('.pdf'),
            isImage: file.type.startsWith('image/'),
        }));

        setAttachments((prev) => [...prev, ...newItems]);

        // Start chunked upload for each newly added file
        newItems.forEach((item) => {
            startFileUpload(item);
        });
    };

    const handleRemoveAttachment = (id) => {
        setAttachments((prev) => prev.filter((item) => item.id !== id));
        setAttachmentError('');
    };

    const handleRetryUpload = (uploadItem) => {
        startFileUpload(uploadItem);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setErrors({});
        setServerError('');
        setAttachmentError('');

        if (hasUploadingFiles) {
            setAttachmentError('Please wait for file uploads to complete.');
            return;
        }

        const failedFiles = attachments.filter((item) => item.status === 'error');
        if (failedFiles.length > 0) {
            setAttachmentError('Please remove or retry failed file uploads before submitting.');
            return;
        }

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

        const completedAttachmentIds = attachments
            .filter((item) => item.status === 'completed' && item.attachmentId)
            .map((item) => item.attachmentId);

        createMutation.mutate({
            subject: subject.trim(),
            description: description.trim(),
            priority,
            category_id: parseInt(categoryId, 10),
            attachment_ids: completedAttachmentIds,
        });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={handleClose}
            title="Create Support Ticket"
            description="Submit a new incident or service request with automatic idempotency protection and chunked file attachments."
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
                        rows={3}
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

                {/* Attachments Section */}
                <div className="space-y-2 pt-1 border-t border-slate-100 dark:border-slate-800">
                    <div className="flex items-center justify-between text-xs">
                        <span className="font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <Paperclip className="w-3.5 h-3.5 text-indigo-500" />
                            Attachments ({attachments.length}/{MAX_FILES_COUNT})
                        </span>
                        <span className="text-[11px] text-slate-400">
                            {formatBytes(totalAttachedSize)} / {formatBytes(MAX_TOTAL_SIZE)} (Max 5MB/file)
                        </span>
                    </div>

                    {/* Drag and Drop Zone */}
                    <div
                        onDragOver={(e) => {
                            e.preventDefault();
                            setIsDragging(true);
                        }}
                        onDragLeave={() => setIsDragging(false)}
                        onDrop={(e) => {
                            e.preventDefault();
                            setIsDragging(false);
                            if (e.dataTransfer.files) {
                                handleFilesAdded(e.dataTransfer.files);
                            }
                        }}
                        onClick={() => fileInputRef.current?.click()}
                        className={`border-2 border-dashed rounded-lg p-3 text-center cursor-pointer transition-colors ${
                            isDragging
                                ? 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-950/20'
                                : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40'
                        }`}
                    >
                        <input
                            ref={fileInputRef}
                            type="file"
                            multiple
                            accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg"
                            className="hidden"
                            onChange={(e) => {
                                if (e.target.files) {
                                    handleFilesAdded(e.target.files);
                                }
                                e.target.value = '';
                            }}
                        />
                        <div className="flex flex-col items-center justify-center gap-1 text-slate-500 dark:text-slate-400">
                            <UploadCloud className="w-5 h-5 text-indigo-500" />
                            <span className="text-xs font-medium">
                                Drag & drop or <span className="text-indigo-600 dark:text-indigo-400 underline">browse</span> files
                            </span>
                            <span className="text-[10px] text-slate-400">
                                PDF, PNG, JPEG up to 5MB each • Resilient chunked upload
                            </span>
                        </div>
                    </div>

                    {attachmentError && (
                        <div className="flex items-center gap-1.5 p-2 text-xs text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 rounded-lg">
                            <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                            <span>{attachmentError}</span>
                        </div>
                    )}

                    {/* Uploaded Files List */}
                    {attachments.length > 0 && (
                        <div className="space-y-1.5 max-h-44 overflow-y-auto pr-1">
                            {attachments.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex items-center justify-between gap-2 p-2 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs"
                                >
                                    <div className="flex items-center gap-2 min-w-0 flex-1">
                                        {item.isPdf ? (
                                            <FileText className="w-4 h-4 text-red-500 shrink-0" />
                                        ) : item.isImage ? (
                                            <ImageIcon className="w-4 h-4 text-blue-500 shrink-0" />
                                        ) : (
                                            <Paperclip className="w-4 h-4 text-slate-400 shrink-0" />
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center justify-between gap-1">
                                                <span className="font-medium text-slate-700 dark:text-slate-200 truncate max-w-[200px]">
                                                    {item.file.name}
                                                </span>
                                                <span className="text-[10px] text-slate-400 shrink-0">
                                                    {formatBytes(item.file.size)}
                                                </span>
                                            </div>

                                            {/* Progress / Status display */}
                                            {item.status === 'uploading' && (
                                                <div className="mt-1 space-y-0.5">
                                                    <div className="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
                                                        <div
                                                            className="bg-indigo-600 h-full transition-all duration-200 rounded-full"
                                                            style={{ width: `${item.progress}%` }}
                                                        />
                                                    </div>
                                                    <div className="flex items-center justify-between text-[10px] text-slate-400">
                                                        <span>Uploading chunk {item.chunkInfo || ''}</span>
                                                        <span>{item.progress}%</span>
                                                    </div>
                                                </div>
                                            )}

                                            {item.status === 'completed' && (
                                                <div className="flex items-center gap-1 text-[10px] text-emerald-600 dark:text-emerald-400 mt-0.5">
                                                    <CheckCircle2 className="w-3 h-3" />
                                                    <span>Ready to attach</span>
                                                </div>
                                            )}

                                            {item.status === 'error' && (
                                                <div className="flex items-center gap-1 text-[10px] text-red-600 dark:text-red-400 mt-0.5">
                                                    <AlertCircle className="w-3 h-3" />
                                                    <span>{item.error || 'Upload error'}</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {/* Action Buttons */}
                                    <div className="flex items-center gap-1 shrink-0">
                                        {item.status === 'error' && (
                                            <button
                                                type="button"
                                                onClick={() => handleRetryUpload(item)}
                                                title="Retry Upload"
                                                className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-500 hover:text-indigo-600"
                                            >
                                                <RefreshCw className="w-3.5 h-3.5" />
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => handleRemoveAttachment(item.id)}
                                            title="Remove"
                                            className="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded text-slate-400 hover:text-red-500"
                                        >
                                            <X className="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <Button type="button" variant="outline" onClick={handleClose} disabled={createMutation.isPending}>
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        loading={createMutation.isPending}
                        disabled={createMutation.isPending || hasUploadingFiles}
                    >
                        {hasUploadingFiles ? 'Uploading Files...' : 'Submit Ticket'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
