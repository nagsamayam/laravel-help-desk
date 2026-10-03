import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';
import { useAuthStore } from '@/stores/auth-store';
import { StatusBadge, PriorityBadge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/Tabs';
import { LoadingSpinner } from '@/components/ui/LoadingSpinner';
import { formatDate } from '@/lib/utils';
import { formatBytes } from '@/lib/chunk-uploader';
import { Modal } from '@/components/ui/Modal';
import {
    ArrowLeft,
    User,
    Calendar,
    GitFork,
    UserCheck,
    RefreshCw,
    MessageSquare,
    History,
    ShieldCheck,
    Clock,
    Paperclip,
    Download,
    ExternalLink,
    FileText,
    Image as ImageIcon,
    Loader2,
    AlertCircle,
} from 'lucide-react';
import { TicketMessages } from './TicketMessages';
import { TicketStatusHistoryTimeline } from './TicketStatusHistoryTimeline';
import { TicketAuditLogs } from './TicketAuditLogs';
import { TicketStateModal } from './TicketStateModal';
import { TicketAssignModal } from './TicketAssignModal';
import { TicketRoutingModal } from './TicketRoutingModal';

export function TicketDetail({ ticketId, onBack }) {
    const { user, isAgent, isAdmin, isCustomer, canChangeTicketState, canViewAuditLogs } = useAuthStore();
    const [isStateModalOpen, setIsStateModalOpen] = useState(false);
    const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
    const [isRoutingModalOpen, setIsRoutingModalOpen] = useState(false);
    const [previewAttachment, setPreviewAttachment] = useState(null);
    const [loadingAttachmentId, setLoadingAttachmentId] = useState(null);
    const [attachmentError, setAttachmentError] = useState(null);

    const { data: ticket, isLoading, isError, error } = useQuery({
        queryKey: queryKeys.tickets.detail(ticketId),
        queryFn: async () => {
            const res = await apiClient.get(`/tickets/${ticketId}`);
            return res.data?.data || res.data;
        },
    });

    const handleDownload = async (attachment) => {
        const actionKey = `download-${attachment.id}`;
        if (loadingAttachmentId) return;
        setLoadingAttachmentId(actionKey);
        setAttachmentError(null);

        try {
            const response = await apiClient.get(`/attachments/${attachment.id}/download`, {
                responseType: 'blob',
            });
            const blob = new Blob([response.data], { type: attachment.mime_type || 'application/octet-stream' });
            const blobUrl = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = blobUrl;
            link.setAttribute('download', attachment.original_name);
            document.body.appendChild(link);
            link.click();
            link.parentNode.removeChild(link);
            setTimeout(() => window.URL.revokeObjectURL(blobUrl), 2000);
        } catch (err) {
            console.error('Failed to download attachment:', err);
            setAttachmentError(`Failed to download ${attachment.original_name}: ${err?.response?.data?.message || err.message}`);
        } finally {
            setLoadingAttachmentId(null);
        }
    };

    const handleView = async (attachment) => {
        const actionKey = `view-${attachment.id}`;
        if (loadingAttachmentId) return;
        setLoadingAttachmentId(actionKey);
        setAttachmentError(null);

        try {
            const response = await apiClient.get(`/attachments/${attachment.id}/view`, {
                responseType: 'blob',
            });
            const blob = new Blob([response.data], { type: attachment.mime_type || 'application/octet-stream' });
            const blobUrl = window.URL.createObjectURL(blob);

            if (attachment.is_image || attachment.is_pdf) {
                setPreviewAttachment({
                    attachment,
                    url: blobUrl,
                });
            } else {
                window.open(blobUrl, '_blank');
                setTimeout(() => window.URL.revokeObjectURL(blobUrl), 60000);
            }
        } catch (err) {
            console.error('Failed to view attachment:', err);
            setAttachmentError(`Failed to open ${attachment.original_name}: ${err?.response?.data?.message || err.message}`);
        } finally {
            setLoadingAttachmentId(null);
        }
    };

    const closePreview = () => {
        if (previewAttachment?.url) {
            window.URL.revokeObjectURL(previewAttachment.url);
        }
        setPreviewAttachment(null);
    };

    if (isLoading) {
        return <LoadingSpinner text="Loading ticket details..." size="lg" />;
    }

    if (isError) {
        return (
            <div className="p-8 text-center text-red-600 bg-red-50 dark:bg-red-950/20 rounded-xl">
                Failed to load ticket #{ticketId}: {error?.message}
                <div className="mt-4">
                    <Button variant="outline" onClick={onBack}>Go Back</Button>
                </div>
            </div>
        );
    }

    if (!ticket) return null;

    const isOverdue = ticket.sla_due_at && new Date(ticket.sla_due_at) < new Date() && ticket.status !== 'closed' && ticket.status !== 'resolved';

    return (
        <div className="space-y-6">
            {/* Top Navigation & Action Controls */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
                <div className="flex items-center gap-3">
                    <Button variant="outline" size="sm" onClick={onBack} className="gap-1.5">
                        <ArrowLeft className="w-4 h-4" />
                        <span>Back</span>
                    </Button>
                    <div>
                        <div className="flex items-center gap-2">
                            <span className="font-mono text-sm font-bold text-slate-500">#{ticket.id}</span>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-slate-100">{ticket.subject || ticket.title}</h1>
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {canChangeTicketState(ticket) && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setIsStateModalOpen(true)}
                            className="gap-1.5"
                        >
                            <RefreshCw className="w-3.5 h-3.5" />
                            <span>
                                {isAgent()
                                    ? 'Transition State'
                                    : ['CLOSED', 'RESOLVED'].includes(String(ticket.status).toUpperCase())
                                    ? 'Reopen Ticket'
                                    : 'Close Ticket'}
                            </span>
                        </Button>
                    )}

                    {isAgent() && (
                        <>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setIsAssignModalOpen(true)}
                                className="gap-1.5"
                            >
                                <UserCheck className="w-3.5 h-3.5" />
                                <span>Assign Agent</span>
                            </Button>

                            <Button
                                size="sm"
                                variant="secondary"
                                onClick={() => setIsRoutingModalOpen(true)}
                                className="gap-1.5"
                            >
                                <GitFork className="w-3.5 h-3.5" />
                                <span>Evaluate Routing</span>
                            </Button>
                        </>
                    )}
                </div>
            </div>

            {/* Ticket Info Card Grid */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-xs">
                <div>
                    <span className="text-slate-400 font-medium block mb-1">Status</span>
                    <StatusBadge status={ticket.status} />
                </div>

                <div>
                    <span className="text-slate-400 font-medium block mb-1">Priority</span>
                    <PriorityBadge priority={ticket.priority} />
                </div>

                <div>
                    <div className="flex items-center justify-between mb-1">
                        <span className="text-slate-400 font-medium block">Assigned To</span>
                        {isAgent() && (
                            <button
                                type="button"
                                onClick={() => setIsAssignModalOpen(true)}
                                className="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1 cursor-pointer"
                            >
                                <UserCheck className="w-3 h-3" />
                                {ticket.assigned_to ? 'Change' : 'Assign'}
                            </button>
                        )}
                    </div>
                    {(() => {
                        const assignee = ticket.assignee || ticket.assigne || ticket.assigned_to_user || ticket.assigned_agent;
                        if (assignee) {
                            return (
                                <div className="flex items-center gap-2">
                                    <div className="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-xs font-bold shrink-0">
                                        {(assignee.name || assignee.full_name || assignee.email || 'A')[0].toUpperCase()}
                                    </div>
                                    <div className="flex flex-col min-w-0">
                                        <span className="font-semibold text-slate-800 dark:text-slate-200 truncate">
                                            {assignee.name || assignee.full_name || 'Agent'}
                                        </span>
                                        {assignee.email && (
                                            <span className="text-[11px] text-slate-400 truncate">
                                                {assignee.email}
                                            </span>
                                        )}
                                        {assignee.role && (
                                            <span className="text-[10px] font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">
                                                {assignee.role}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            );
                        }
                        return (
                            <div className="font-semibold text-slate-400 italic">
                                Unassigned
                            </div>
                        );
                    })()}
                </div>

                <div>
                    <span className="text-slate-400 font-medium block mb-1">Category & Customer</span>
                    <div className="font-semibold text-slate-800 dark:text-slate-200">
                        {ticket.category?.name || 'General Support'}
                    </div>
                    <div className="text-slate-500 text-[11px] mt-0.5">
                        by {ticket.customer?.name || `Customer #${ticket.customer_id}`}
                    </div>
                </div>
            </div>

            {/* Description Card */}
            <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                <h3 className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Description</h3>
                <p className="text-sm text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed">
                    {ticket.description}
                </p>
                <div className="flex items-center gap-4 mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400">
                    <span className="flex items-center gap-1">
                        <Calendar className="w-3.5 h-3.5" /> Created {formatDate(ticket.created_at)}
                    </span>
                    {ticket.sla_due_at && (
                        <span className={`flex items-center gap-1 font-medium ${isOverdue ? 'text-red-500 font-bold' : ''}`}>
                            <Clock className="w-3.5 h-3.5" /> SLA Due: {formatDate(ticket.sla_due_at)}
                        </span>
                    )}
                </div>
            </div>

            {/* Attachments Section */}
            {ticket.attachments && ticket.attachments.length > 0 && (
                <div className="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3">
                    <div className="flex items-center justify-between">
                        <h3 className="text-xs font-semibold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                            <Paperclip className="w-3.5 h-3.5 text-indigo-500" />
                            Attachments ({ticket.attachments.length})
                        </h3>
                    </div>

                    {attachmentError && (
                        <div className="p-3 text-xs rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/50 text-red-700 dark:text-red-300 flex items-center gap-2">
                            <AlertCircle className="w-4 h-4 shrink-0" />
                            <span>{attachmentError}</span>
                        </div>
                    )}

                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                        {ticket.attachments.map((attachment) => {
                            const isViewLoading = loadingAttachmentId === `view-${attachment.id}`;
                            const isDownloadLoading = loadingAttachmentId === `download-${attachment.id}`;

                            return (
                                <div
                                    key={attachment.id}
                                    className="flex flex-col justify-between p-3 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 text-xs gap-3 group hover:border-indigo-300 dark:hover:border-indigo-800 transition-colors"
                                >
                                    <div className="flex items-start gap-2.5 min-w-0">
                                        <div className="p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shrink-0">
                                            {attachment.is_pdf ? (
                                                <FileText className="w-5 h-5 text-red-500" />
                                            ) : attachment.is_image ? (
                                                <ImageIcon className="w-5 h-5 text-blue-500" />
                                            ) : (
                                                <Paperclip className="w-5 h-5 text-slate-400" />
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="font-semibold text-slate-800 dark:text-slate-200 truncate" title={attachment.original_name}>
                                                {attachment.original_name}
                                            </p>
                                            <p className="text-[11px] text-slate-400 mt-0.5">
                                                {formatBytes(attachment.file_size)} • {attachment.mime_type?.split('/')[1]?.toUpperCase() || 'FILE'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 pt-2 border-t border-slate-200/60 dark:border-slate-800/60">
                                        <button
                                            type="button"
                                            disabled={isViewLoading || isDownloadLoading}
                                            onClick={() => handleView(attachment)}
                                            className="flex-1 flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-[11px] font-medium bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50 transition-colors cursor-pointer"
                                        >
                                            {isViewLoading ? (
                                                <Loader2 className="w-3 h-3 animate-spin text-slate-500" />
                                            ) : (
                                                <ExternalLink className="w-3 h-3 text-slate-500" />
                                            )}
                                            <span>{isViewLoading ? 'Loading...' : 'View'}</span>
                                        </button>
                                        <button
                                            type="button"
                                            disabled={isViewLoading || isDownloadLoading}
                                            onClick={() => handleDownload(attachment)}
                                            className="flex-1 flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-lg text-[11px] font-medium bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-900 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 disabled:opacity-50 transition-colors cursor-pointer"
                                        >
                                            {isDownloadLoading ? (
                                                <Loader2 className="w-3 h-3 animate-spin text-indigo-500" />
                                            ) : (
                                                <Download className="w-3 h-3 text-indigo-600 dark:text-indigo-400" />
                                            )}
                                            <span>{isDownloadLoading ? 'Downloading...' : 'Download'}</span>
                                        </button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Tabs Section: Discussion, Status History, Audit Logs */}
            <Tabs defaultValue="discussion" className="w-full">
                <TabsList className={`grid w-full ${canViewAuditLogs() ? 'grid-cols-3 max-w-md' : 'grid-cols-2 max-w-xs'}`}>
                    <TabsTrigger value="discussion" className="gap-1.5">
                        <MessageSquare className="w-3.5 h-3.5" />
                        <span>Discussion</span>
                    </TabsTrigger>
                    <TabsTrigger value="history" className="gap-1.5">
                        <History className="w-3.5 h-3.5" />
                        <span>Status History</span>
                    </TabsTrigger>
                    {canViewAuditLogs() && (
                        <TabsTrigger value="audit" className="gap-1.5">
                            <ShieldCheck className="w-3.5 h-3.5" />
                            <span>Audit Trail</span>
                        </TabsTrigger>
                    )}
                </TabsList>

                <TabsContent value="discussion">
                    <TicketMessages ticketId={ticket.id} />
                </TabsContent>

                <TabsContent value="history">
                    <TicketStatusHistoryTimeline ticketId={ticket.id} />
                </TabsContent>

                {canViewAuditLogs() && (
                    <TabsContent value="audit">
                        <TicketAuditLogs ticketId={ticket.id} />
                    </TabsContent>
                )}
            </Tabs>

            {/* Modals */}
            <TicketStateModal
                isOpen={isStateModalOpen}
                onClose={() => setIsStateModalOpen(false)}
                ticket={ticket}
            />

            <TicketAssignModal
                isOpen={isAssignModalOpen}
                onClose={() => setIsAssignModalOpen(false)}
                ticket={ticket}
            />

            <TicketRoutingModal
                isOpen={isRoutingModalOpen}
                onClose={() => setIsRoutingModalOpen(false)}
                ticket={ticket}
            />

            {/* Attachment Preview Modal */}
            <Modal
                isOpen={!!previewAttachment}
                onClose={closePreview}
                title={previewAttachment?.attachment?.original_name || 'Attachment Preview'}
                description={previewAttachment?.attachment ? `${formatBytes(previewAttachment.attachment.file_size)} • ${previewAttachment.attachment.mime_type}` : ''}
                maxWidth="max-w-4xl"
            >
                {previewAttachment && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-center p-2 rounded-xl bg-slate-100 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 min-h-[300px] max-h-[75vh] overflow-auto">
                            {previewAttachment.attachment.is_image ? (
                                <img
                                    src={previewAttachment.url}
                                    alt={previewAttachment.attachment.original_name}
                                    className="max-h-[70vh] max-w-full rounded-lg object-contain shadow-sm"
                                />
                            ) : previewAttachment.attachment.is_pdf ? (
                                <iframe
                                    src={previewAttachment.url}
                                    title={previewAttachment.attachment.original_name}
                                    className="w-full h-[70vh] rounded-lg border-0 shadow-sm"
                                />
                            ) : (
                                <p className="text-sm text-slate-500">Preview not available for this file type.</p>
                            )}
                        </div>

                        <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                            <a
                                href={previewAttachment.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                            >
                                <ExternalLink className="w-3.5 h-3.5" />
                                <span>Open in New Tab</span>
                            </a>
                            <Button
                                size="sm"
                                onClick={() => handleDownload(previewAttachment.attachment)}
                                className="gap-1.5"
                            >
                                <Download className="w-3.5 h-3.5" />
                                <span>Download</span>
                            </Button>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
