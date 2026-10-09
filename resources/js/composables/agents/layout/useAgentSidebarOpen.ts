/**
 * Whether the agent sidebar is expanded. Remembered in localStorage under
 * `agent-sidebar-open` so it survives page visits and reloads; open by default.
 */
import { ref, watch } from 'vue';

const STORAGE_KEY = 'agent-sidebar-open';

export function useAgentSidebarOpen() {
    const saved = localStorage.getItem(STORAGE_KEY);
    const sidebarOpen = ref(saved !== null ? saved === 'true' : true);

    watch(sidebarOpen, (open) => {
        localStorage.setItem(STORAGE_KEY, open.toString());
    });

    return { sidebarOpen };
}
