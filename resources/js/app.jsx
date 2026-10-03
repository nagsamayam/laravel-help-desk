import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider, useQuery } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/auth-store';
import { useUiStore } from '@/stores/ui-store';
import { useBroadcasting } from '@/hooks/use-broadcasting';
import { apiClient } from '@/lib/api-client';
import { queryKeys } from '@/lib/query-keys';

import { Navbar } from '@/components/layout/Navbar';
import { Sidebar } from '@/components/layout/Sidebar';
import { ToastContainer } from '@/components/ui/ToastContainer';
import { BroadcastNotificationsDrawer } from '@/components/broadcasting/BroadcastNotificationsDrawer';
import { AuthPage } from '@/features/auth/AuthPage';
import { TicketList } from '@/features/tickets/TicketList';
import { TicketFilterBar } from '@/features/tickets/TicketFilterBar';
import { TicketDetail } from '@/features/tickets/TicketDetail';
import { TicketCreateModal } from '@/features/tickets/TicketCreateModal';
import { TicketAssignModal } from '@/features/tickets/TicketAssignModal';
import { AuditLogList } from '@/features/audit/AuditLogList';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            retry: 1,
            refetchOnWindowFocus: false,
            staleTime: 1000 * 30, // 30 seconds
        },
    },
});

function MainDashboard() {
    const { user, isAuthenticated, isAgent, fetchProfile } = useAuthStore();
    const { connectionStatus } = useBroadcasting();
    const [currentView, setCurrentView] = useState('tickets');
    const [selectedTicketId, setSelectedTicketId] = useState(null);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [assigningTicket, setAssigningTicket] = useState(null);

    useEffect(() => {
        if (isAuthenticated) {
            fetchProfile();
        }
    }, [isAuthenticated, fetchProfile]);

    const [filters, setFilters] = useState({
        search: '',
        status: '',
        priority: '',
        urgent: '',
        overdue: '',
        unassigned: '',
        page: 1,
    });

    const { data: ticketsResponse, isLoading, isError, error } = useQuery({
        queryKey: queryKeys.tickets.list(filters),
        queryFn: async () => {
            const params = new URLSearchParams();
            if (filters.search) params.append('search', filters.search);
            if (filters.status) params.append('status', filters.status);
            if (filters.priority) params.append('priority', filters.priority);
            if (filters.urgent) params.append('urgent', filters.urgent);
            if (filters.overdue) params.append('overdue', filters.overdue);
            if (filters.unassigned) params.append('unassigned', filters.unassigned);
            if (filters.page) params.append('page', filters.page);

            const res = await apiClient.get(`/tickets?${params.toString()}`);
            return res.data;
        },
        enabled: isAuthenticated && currentView === 'tickets' && !selectedTicketId,
    });

    const tickets = ticketsResponse?.data || [];
    const pagination = ticketsResponse?.meta || null;

    const handleFilterChange = (newFilters) => {
        setFilters({ ...newFilters, page: 1 });
    };

    const handleResetFilters = () => {
        setFilters({
            search: '',
            status: '',
            priority: '',
            urgent: '',
            overdue: '',
            unassigned: '',
            page: 1,
        });
    };

    const handleSelectTicket = (id) => {
        setCurrentView('tickets');
        setSelectedTicketId(id);
    };

    const handleBackToList = () => {
        setSelectedTicketId(null);
    };

    const handleChangeView = (view) => {
        setCurrentView(view);
        setSelectedTicketId(null);
    };

    if (!isAuthenticated) {
        return <AuthPage />;
    }

    return (
        <div className="min-h-screen flex flex-col bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100">
            <Navbar
                connectionStatus={connectionStatus}
                onOpenCreateTicket={() => setIsCreateModalOpen(true)}
            />

            <div className="flex flex-1">
                <Sidebar currentView={currentView} onChangeView={handleChangeView} />

                <main className="flex-1 p-4 md:p-8 max-w-7xl mx-auto w-full">
                    {currentView === 'tickets' && (
                        selectedTicketId ? (
                            <TicketDetail ticketId={selectedTicketId} onBack={handleBackToList} />
                        ) : (
                            <div className="space-y-6">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div>
                                        <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                                            Support Tickets
                                        </h1>
                                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                            Manage customer requests, monitor SLAs, and execute state transitions.
                                        </p>
                                    </div>
                                </div>

                                <TicketFilterBar
                                    filters={filters}
                                    onFilterChange={handleFilterChange}
                                    onReset={handleResetFilters}
                                />

                                <TicketList
                                    tickets={tickets}
                                    pagination={pagination}
                                    isLoading={isLoading}
                                    isError={isError}
                                    error={error}
                                    onSelectTicket={handleSelectTicket}
                                    onAssignTicket={(ticket) => setAssigningTicket(ticket)}
                                    onPageChange={(page) => setFilters((f) => ({ ...f, page }))}
                                />
                            </div>
                        )
                    )}

                    {currentView === 'audit-logs' && <AuditLogList />}
                </main>
            </div>

            <TicketCreateModal
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
            />

            <TicketAssignModal
                isOpen={Boolean(assigningTicket)}
                onClose={() => setAssigningTicket(null)}
                ticket={assigningTicket}
            />

            <BroadcastNotificationsDrawer
                onSelectTicket={handleSelectTicket}
                connectionStatus={connectionStatus}
            />

            <ToastContainer />
        </div>
    );
}

export default function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <MainDashboard />
        </QueryClientProvider>
    );
}

const rootElement = document.getElementById('root');
if (rootElement) {
    createRoot(rootElement).render(<App />);
}
