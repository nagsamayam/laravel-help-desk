import React from 'react';
import { useAuthStore } from '@/stores/auth-store';
import { useUiStore } from '@/stores/ui-store';
import { useBroadcastStore } from '@/stores/broadcast-store';
import { Menu, LogOut, User, ShieldCheck, Ticket, PlusCircle, Radio, Bell } from 'lucide-react';
import { Button } from '@/components/ui/Button';

export function Navbar({ onOpenCreateTicket, connectionStatus = 'connected' }) {
    const { user, logout, isAgent, canCreateTicket } = useAuthStore();
    const { toggleSidebar } = useUiStore();
    const { messages, toggleOpen: toggleBroadcastDrawer } = useBroadcastStore();

    const isLive = connectionStatus === 'connected';
    const isConnecting = connectionStatus === 'connecting';
    const unreadCount = messages.filter((m) => !m.read).length;

    return (
        <header className="sticky top-0 z-40 flex h-16 w-full items-center justify-between border-b border-slate-200 bg-white/80 px-4 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/80">
            <div className="flex items-center gap-3">
                <button
                    onClick={toggleSidebar}
                    className="p-2 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    aria-label="Toggle navigation"
                >
                    <Menu className="w-5 h-5" />
                </button>
                <div className="flex items-center gap-2">
                    <div className="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold shadow-sm shadow-indigo-500/30">
                        <Ticket className="w-5 h-5" />
                    </div>
                    <span className="font-bold text-slate-900 dark:text-slate-100 hidden sm:inline-block">
                        DeskSphere
                    </span>
                    <span className="text-xs bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-400 font-semibold px-2 py-0.5 rounded-full border border-indigo-200/50 dark:border-indigo-800/50">
                        React 19 + Laravel
                    </span>
                </div>
            </div>

            <div className="flex items-center gap-3">
                {/* Real-time Reverb Indicator (Click to open broadcast drawer) */}
                <button
                    onClick={toggleBroadcastDrawer}
                    title={`Real-time WebSocket Status: ${connectionStatus} (Click to open broadcast feed)`}
                    className={`hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border transition-colors hover:opacity-90 ${
                        isLive
                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60'
                            : isConnecting
                            ? 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/60'
                            : 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700'
                    }`}
                >
                    <span
                        className={`w-2 h-2 rounded-full ${
                            isLive
                                ? 'bg-emerald-500 animate-pulse'
                                : isConnecting
                                ? 'bg-amber-500 animate-ping'
                                : 'bg-slate-400'
                        }`}
                    />
                    <span>{isLive ? 'Live' : isConnecting ? 'Connecting...' : 'Offline'}</span>
                </button>

                {/* Broadcast Messages & Notifications Bell Button */}
                <button
                    onClick={toggleBroadcastDrawer}
                    title="Real-time Broadcast Notifications"
                    className="relative p-2 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    <Bell className="w-5 h-5" />
                    {unreadCount > 0 ? (
                        <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] font-bold text-white shadow-xs animate-in zoom-in">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    ) : isLive ? (
                        <span className="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900" />
                    ) : null}
                </button>

                {canCreateTicket() && (
                    <Button
                        size="sm"
                        variant="default"
                        onClick={onOpenCreateTicket}
                        className="gap-1.5 shadow-sm shadow-indigo-600/20"
                    >
                        <PlusCircle className="w-4 h-4" />
                        <span>New Ticket</span>
                    </Button>
                )}

                <div className="flex items-center gap-2 border-l border-slate-200 dark:border-slate-800 pl-3">
                    <div className="flex flex-col text-right hidden sm:flex">
                        <span className="text-xs font-semibold text-slate-800 dark:text-slate-200">{user?.name}</span>
                        <span className="text-[10px] text-slate-500 capitalize">{user?.role}</span>
                    </div>

                    <div className="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        <User className="w-4 h-4" />
                    </div>

                    <button
                        onClick={logout}
                        title="Sign Out"
                        className="p-2 text-slate-400 hover:text-red-600 dark:hover:text-red-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                    >
                        <LogOut className="w-4 h-4" />
                    </button>
                </div>
            </div>
        </header>
    );
}
