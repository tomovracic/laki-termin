import { useEffect, useState } from 'react';

const NAV_TOKEN_COUNT_EVENT = 'nav-token-count-update';

export function publishNavTokenCount(count: number): void {
    window.dispatchEvent(
        new CustomEvent<number>(NAV_TOKEN_COUNT_EVENT, { detail: count }),
    );
}

export function useNavTokenCount(initialCount: number): number {
    const [count, setCount] = useState(initialCount);

    useEffect(() => {
        setCount(initialCount);
    }, [initialCount]);

    useEffect(() => {
        function handleUpdate(event: Event): void {
            const nextCount = (event as CustomEvent<number>).detail;

            if (typeof nextCount === 'number') {
                setCount(nextCount);
            }
        }

        window.addEventListener(NAV_TOKEN_COUNT_EVENT, handleUpdate);

        return () => {
            window.removeEventListener(NAV_TOKEN_COUNT_EVENT, handleUpdate);
        };
    }, []);

    return count;
}
