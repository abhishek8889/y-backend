import { useEffect } from 'react';

/**
 * Removes the critical CSS initial page loader once React has mounted.
 */
export default function HideInitialLoader() {
    useEffect(() => {
        document.getElementById('initial-loader')?.remove();
        document.documentElement.classList.remove('initial-loading');
    }, []);

    return null;
}
