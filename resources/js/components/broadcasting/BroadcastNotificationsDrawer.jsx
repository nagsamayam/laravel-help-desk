import React, { useState } from 'react';
import { useBroadcastStore } from '@/stores/broadcast-store';
import {
    Radio,
    X,
    CheckCheck,
    Trash2,
    Ticket,
    RefreshCw,
    UserCheck,
    MessageSquare,
    ChevronDown,
    ChevronUp,
    ExternalLink,
    Code,
    Sparkles,
} from 'lucide-react';
import { Button } from '@/components/ui/Button';

function formatTimestamp(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString);
    const now = new Date();
    const diffSeconds = Math.floor((now - date) / 1000);

    if (diffSeconds < 5) return 'Just now';
    if (diffSeconds < 60) return `${diffSeconds}s ago`;
    const diffMinutes = Math.floor(diffSeconds / 60);
    if (diffMinutes < 60) return `${diffMinutes}m ago`;
    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function getEventIcon(type) {
    switch (type) {
        case 'ticket.created':
            return <Ticket className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />;
        case 'ticket.status.changed':
            return <RefreshCw className="w-4 h-4 text-blue-600 dark:text-blue-400" />;
        case 'ticket.assigned':
            return <UserCheck className="w-4 h-4 text-purple-600 dark:text-purple-400" />;
        case 'ticket.message.added':
            return <MessageSquare className="w-4 h-4 text-amber-600 dark:text-amber-400" />;
        default:
            return <Radio className="w-4 h-4 text-indigo-600 dark:text-indigo-400" />;
    }
}

function getEventTypeBadge(type) {
    switch (type) {
        case 'ticket.created':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800';
        case 'ticket.status.changed':
            return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/50 dark:text-blue-300 dark:border-blue-800';
        case 'ticket.assigned':
            return 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/50 dark:text-purple-300 dark:border-purple-800';
        case 'ticket.message.added':
            return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
    }
}

export function BroadcastNotificationsDrawer({ onSelectTicket, connectionStatus = 'connected' }) {
    const { messages, isOpen, setIsOpen, markAsRead, markAllAsRead, clearAll } = useBroadcastStore();
    const [filterCategory, setFilterCategory] = useState('all');
    const [expandedPayloads, setExpandedPayloads] = useState({});

    if (!isOpen) return null;

    const unreadCount = messages.filter((m) => !m.read).length;

    const filteredMessages = messages.filter((msg) => {
        if (filterCategory === 'all') return true;
        if (filterCategory === 'tickets') return msg.type === 'ticket.created' || msg.type === 'ticket.status.changed';
        if (filterCategory === 'assignments') return msg.type === 'ticket.assigned';
        if (filterCategory === 'messages') return msg.type === 'ticket.message.added';
        return true;
    });

    const togglePayload = (id) => {
        setExpandedPayloads((prev) => ({
            ...prev,
            [id]: !prev[id],
        }));
    };

    const handleSelectTicket = (msg) => {
        markAsRead(msg.id);
        if (msg.ticketId && onSelectTicket) {
            onSelectTicket(msg.ticketId);
            setIsOpen(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 overflow-hidden">
            {/* Backdrop */}
            <div
                className="absolute inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity animate-in fade-in"
                onClick={() => setIsOpen(false)}
            />

            <div className="fixed inset-y-0 right-0 max-w-full flex pl-10">
                <div className="w-screen max-w-md bg-white dark:bg-slate-900 shadow-2xl flex flex-col border-l border-slate-200 dark:border-slate-800 animate-in slide-in-from-right duration-300">
                    {/* Header */}
                    <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
                        <div className="flex items-center gap-2.5">
                            <div className="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/50 dark:border-indigo-800/50">
                                <Radio className="w-4 h-4 animate-pulse" />
                            </div>
                            <div>
                                <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                    Broadcast Activity
                                    {unreadCount > 0 && (
                                        <span className="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-600 text-white">
                                            {unreadCount} new
                                        </span>
                                    )}
                                </h3>
                                <p className="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mt-0.5">
                                    <span
                                        className={`w-1.5 h-1.5 rounded-full ${
                                            connectionStatus === 'connected'
                                                ? 'bg-emerald-500'
                                                : connectionStatus === 'connecting'
                                                ? 'bg-amber-500'
                                                : 'bg-slate-400'
                                        }`}
                                    />
                                    <span>Reverb Channel Listener ({connectionStatus})</span>
                                </p>
                            </div>
                        </div>

                        <button
                            onClick={() => setIsOpen(false)}
                            className="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    {/* Filter Pills & Toolbar */}
                    <div className="px-4 py-2.5 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center justify-between gap-2 text-xs">
                        <div className="flex items-center gap-1 overflow-x-auto no-scrollbar">
                            {[
                                { id: 'all', label: 'All', count: messages.length },
                                {
                                    id: 'tickets',
                                    label: 'Tickets',
                                    count: messages.filter((m) => m.type.startsWith('ticket.') && m.type !== 'ticket.assigned' && m.type !== 'ticket.message.added').length,
                                },
                                {
                                    id: 'messages',
                                    label: 'Replies',
                                    count: messages.filter((m) => m.type === 'ticket.message.added').length,
                                },
                                {
                                    id: 'assignments',
                                    label: 'Assigned',
                                    count: messages.filter((m) => m.type === 'ticket.assigned').length,
                                },
                            ].map((tab) => (
                                <button
                                    key={tab.id}
                                    onClick={() => setFilterCategory(tab.id)}
                                    className={`px-2.5 py-1 rounded-lg font-medium transition-colors text-[11px] whitespace-nowrap flex items-center gap-1.5 ${
                                        filterCategory === tab.id
                                            ? 'bg-indigo-600 text-white'
                                            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                    }`}
                                >
                                    <span>{tab.label}</span>
                                    {tab.count > 0 && (
                                        <span
                                            className={`px-1.5 py-0.2 rounded-full text-[10px] ${
                                                filterCategory === tab.id
                                                    ? 'bg-indigo-700 text-white'
                                                    : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'
                                            }`}
                                        >
                                            {tab.count}
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>

                        {messages.length > 0 && (
                            <div className="flex items-center gap-1 shrink-0">
                                {unreadCount > 0 && (
                                    <button
                                        onClick={markAllAsRead}
                                        title="Mark all as read"
                                        className="p-1 text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 rounded hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                    >
                                        <CheckCheck className="w-4 h-4" />
                                    </button>
                                )}
                                <button
                                    onClick={clearAll}
                                    title="Clear all messages"
                                    className="p-1 text-slate-400 hover:text-red-600 dark:hover:text-red-400 rounded hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                                >
                                    <Trash2 className="w-4 h-4" />
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Messages Feed */}
                    <div className="flex-1 overflow-y-auto p-4 space-y-3">
                        {filteredMessages.length === 0 ? (
                            <div className="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400">
                                <div className="w-12 h-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center text-indigo-500 mb-3 border border-indigo-200/50 dark:border-indigo-800/50">
                                    <Sparkles className="w-6 h-6" />
                                </div>
                                <h4 className="text-sm font-semibold text-slate-800 dark:text-slate-200">
                                    No Broadcast Events Yet
                                </h4>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-[260px]">
                                    Live events streamed from Laravel Reverb (such as new tickets, replies, and status updates) will appear here instantly.
                                </p>
                            </div>
                        ) : (
                            filteredMessages.map((msg) => {
                                const isExpanded = !!expandedPayloads[msg.id];
                                return (
                                    <div
                                        key={msg.id}
                                        onClick={() => !msg.read && markAsRead(msg.id)}
                                        className={`group relative p-3.5 rounded-xl border transition-all ${
                                            !msg.read
                                                ? 'bg-indigo-50/40 dark:bg-indigo-950/20 border-indigo-200 dark:border-indigo-800/80 shadow-xs'
                                                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800'
                                        }`}
                                    >
                                        {!msg.read && (
                                            <span className="absolute top-3.5 right-3.5 w-2 h-2 rounded-full bg-indigo-600" />
                                        )}

                                        <div className="flex items-start gap-3">
                                            <div className="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 shrink-0 mt-0.5">
                                                {getEventIcon(msg.type)}
                                            </div>

                                            <div className="flex-1 min-w-0 pr-4">
                                                <div className="flex flex-wrap items-center gap-1.5 mb-1">
                                                    <span
                                                        className={`text-[10px] font-semibold px-2 py-0.5 rounded-full border ${getEventTypeBadge(
                                                            msg.type
                                                        )}`}
                                                    >
                                                        {msg.type}
                                                    </span>
                                                    {msg.channel && (
                                                        <span className="text-[10px] font-mono text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800/80 px-1.5 py-0.5 rounded">
                                                            {msg.channel}
                                                        </span>
                                                    )}
                                                </div>

                                                <h5 className="text-xs font-bold text-slate-900 dark:text-slate-100 leading-snug">
                                                    {msg.title}
                                                </h5>
                                                {msg.description && (
                                                    <p className="text-xs text-slate-600 dark:text-slate-300 mt-0.5 leading-relaxed">
                                                        {msg.description}
                                                    </p>
                                                )}

                                                <div className="flex items-center justify-between gap-2 mt-3 pt-2 border-t border-slate-100 dark:border-slate-800/80 text-[11px] text-slate-400">
                                                    <span>{formatTimestamp(msg.timestamp)}</span>

                                                    <div className="flex items-center gap-2">
                                                        {msg.payload && (
                                                            <button
                                                                type="button"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    togglePayload(msg.id);
                                                                }}
                                                                className="inline-flex items-center gap-1 text-[11px] font-medium text-slate-500 hover:text-slate-700 dark:hover:text-slate-300"
                                                            >
                                                                <Code className="w-3 h-3" />
                                                                <span>{isExpanded ? 'Hide Data' : 'Payload'}</span>
                                                                {isExpanded ? (
                                                                    <ChevronUp className="w-3 h-3" />
                                                                ) : (
                                                                    <ChevronDown className="w-3 h-3" />
                                                                )}
                                                            </button>
                                                        )}

                                                        {msg.ticketId && (
                                                            <button
                                                                type="button"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    handleSelectTicket(msg);
                                                                }}
                                                                className="inline-flex items-center gap-1 font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300"
                                                            >
                                                                <span>View Ticket</span>
                                                                <ExternalLink className="w-3 h-3" />
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>

                                                {/* Expandable JSON payload viewer */}
                                                {isExpanded && msg.payload && (
                                                    <div className="mt-2.5 p-2.5 rounded-lg bg-slate-950 text-slate-200 font-mono text-[10px] overflow-x-auto max-h-48 border border-slate-800">
                                                        <pre>{JSON.stringify(msg.payload, null, 2)}</pre>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
