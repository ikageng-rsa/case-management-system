import { useCallback, useEffect, useId, useMemo, useState } from 'react';

/*
 * On iOS a modal cannot be presented from the root while another is showing,
 * so a confirm or toast asked for while a Sheet is open would never appear.
 * Each open Sheet registers as a host, and the dialog and toasts render inside
 * the topmost one instead of at the root.
 */
export interface HostStack {
    top: string | null;
    register: (id: string) => () => void;
}

export function useHostStack(): HostStack {
    const [hosts, setHosts] = useState<string[]>([]);

    const register = useCallback((id: string) => {
        setHosts((current) => [...current, id]);

        return () => setHosts((current) => current.filter((host) => host !== id));
    }, []);

    return useMemo(() => ({ top: hosts[hosts.length - 1] ?? null, register }), [hosts, register]);
}

export function useHost(register?: HostStack['register']): string {
    const id = useId();

    useEffect(() => register?.(id), [register, id]);

    return id;
}
