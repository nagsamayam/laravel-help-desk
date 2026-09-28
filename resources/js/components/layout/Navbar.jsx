import React from 'react';
import { useAuthStore } from '@/stores/auth-store';
import { useUiStore } from '@/stores/ui-store';
import { Menu, LogOut, User, ShieldCheck, Ticket, PlusCircle } from 'lucide-react';
import { Button } from '@/components/ui/Button';

export function Navbar({ onOpenCreateTicket }) {
    const { user, logout, isAgent, canCreateTicket } = useAuthStore();
    const { toggleSidebar } = useUiStore();

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
