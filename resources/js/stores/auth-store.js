import { create } from 'zustand';

export const useAuthStore = create((set, get) => ({
    user: (() => {
        try {
            const raw = localStorage.getItem('auth_user');
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    })(),
    token: localStorage.getItem('auth_token') || null,
    isAuthenticated: !!localStorage.getItem('auth_token'),
    isLoadingProfile: false,

    setAuth: (user, token) => {
        if (token) {
            localStorage.setItem('auth_token', token);
        }
        if (user) {
            localStorage.setItem('auth_user', JSON.stringify(user));
        }
        set({
            user: user ?? get().user,
            token: token || get().token,
            isAuthenticated: true,
        });
    },

    setUser: (user) => {
        if (user) {
            localStorage.setItem('auth_user', JSON.stringify(user));
        } else {
            localStorage.removeItem('auth_user');
        }
        set({ user });
    },

    fetchProfile: async () => {
        const token = get().token;
        if (!token) return null;
        try {
            set({ isLoadingProfile: true });
            const res = await fetch('/api/v1/auth/me', {
                headers: {
                    Authorization: `Bearer ${token}`,
                    Accept: 'application/json',
                },
            });
            if (res.ok) {
                const data = await res.json();
                const userData = data?.data || data;
                if (userData && userData.id) {
                    get().setUser(userData);
                    return userData;
                }
            } else if (res.status === 401) {
                get().logout();
            }
        } catch {
            // Keep existing offline cached user if network fails
        } finally {
            set({ isLoadingProfile: false });
        }
        return get().user;
    },

    logout: () => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('auth_user');
        set({
            user: null,
            token: null,
            isAuthenticated: false,
        });
    },

    hasRole: (roles) => {
        const user = get().user;
        if (!user || !user.role) return false;
        const currentRole = String(user.role).toUpperCase();
        if (Array.isArray(roles)) {
            return roles.map((r) => String(r).toUpperCase()).includes(currentRole);
        }
        return currentRole === String(roles).toUpperCase();
    },

    isAdmin: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return role === 'ADMIN';
    },

    isAgent: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return ['ADMIN', 'AGENT'].includes(role);
    },

    isCustomer: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return role === 'CUSTOMER';
    },

    canCreateTicket: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return ['ADMIN', 'CUSTOMER'].includes(role);
    },

    canManageTicket: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return ['ADMIN', 'AGENT'].includes(role);
    },

    canViewAuditLogs: () => {
        const role = String(get().user?.role || '').toUpperCase();
        return ['ADMIN', 'AGENT'].includes(role);
    },

    canChangeTicketState: (ticket) => {
        const user = get().user;
        if (!user) return false;
        const role = String(user.role || '').toUpperCase();
        if (['ADMIN', 'AGENT'].includes(role)) return true;
        if (role === 'CUSTOMER' && ticket) {
            return ticket.customer_id === user.id;
        }
        return false;
    },
}));
