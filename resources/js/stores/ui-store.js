import { create } from 'zustand';

export const useUiStore = create((set, get) => ({
    sidebarOpen: true,
    toasts: [],
    activeModal: null,
    modalData: null,

    toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),
    setSidebarOpen: (open) => set({ sidebarOpen: open }),

    openModal: (name, data = null) => set({ activeModal: name, modalData: data }),
    closeModal: () => set({ activeModal: null, modalData: null }),

    addToast: ({ type = 'info', title, message, duration = 4000 }) => {
        const id = Math.random().toString(36).substring(2, 9);
        const newToast = { id, type, title, message };
        set((state) => ({ toasts: [...state.toasts, newToast] }));

        if (duration > 0) {
            setTimeout(() => {
                get().removeToast(id);
            }, duration);
        }
        return id;
    },

    removeToast: (id) => {
        set((state) => ({
            toasts: state.toasts.filter((t) => t.id !== id),
        }));
    },
}));
