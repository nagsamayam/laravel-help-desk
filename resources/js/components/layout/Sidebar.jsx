import React from 'react';
import { useAuthStore } from '@/stores/auth-store';
import { useUiStore } from '@/stores/ui-store';
import { Ticket, Activity, ShieldCheck, Inbox, Clock, CheckSquare } from 'lucide-react';
import { cn } from '@/lib/utils';

export function Sidebar({ currentView, onChangeView }) {
    const { isAgent, isAdmin } = useAuthStore();
    const { sidebarOpen } = useUiStore();

    const navItems = [
        { id: 'tickets', label: 'All Tickets', icon: Ticket, roles: ['admin', 'agent', 'customer'] },
        { id: 'audit-logs', label: 'Audit Trail', icon: Activity, roles: ['admin', 'agent'] },
    ];

    if (!sidebarOpen) return null;

    return (
        <aside className="w-64 border-r border-slate-200 bg-white/50 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-900/50 flex flex-col shrink-0 min-h-[calc(100vh-4rem)]">
            <div className="p-4 space-y-1">
                <div className="text-[11px] font-semibold tracking-wider text-slate-400 dark:text-slate-500 uppercase px-3 py-2">
                    Navigation
                </div>

                {navItems.map((item) => {
                    const hasAccess = item.roles.some((r) => useAuthStore.getState().hasRole(r));
                    if (!hasAccess) return null;

                    const Icon = item.icon;
                    const isActive = currentView === item.id;

                    return (
                        <button
                            key={item.id}
                            onClick={() => onChangeView(item.id)}
                            className={cn(
                                'w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all text-left cursor-pointer',
                                isActive
                                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400 font-semibold shadow-xs'
                                    : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100'
                            )}
                        >
                            <Icon className={cn('w-4 h-4 shrink-0', isActive ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400')} />
                            <span>{item.label}</span>
                        </button>
                    );
                })}
            </div>

            <div className="mt-auto p-4 border-t border-slate-200 dark:border-slate-800">
                <div className="rounded-xl bg-slate-50 dark:bg-slate-800/60 p-3 border border-slate-200/60 dark:border-slate-700/60 text-xs">
                    <div className="font-semibold text-slate-800 dark:text-slate-200 mb-1">Architecture Active</div>
                    <p className="text-slate-500 dark:text-slate-400 text-[11px]">
                        • State Pattern<br />
                        • Strategy Assignment<br />
                        • Chain of Responsibility<br />
                        • Idempotency (UUIDv4)
                    </p>
                </div>
            </div>
        </aside>
    );
}
