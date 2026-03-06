function readCookieValue(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const prefix = `${name}=`;
    const cookieEntry = document.cookie
        .split(';')
        .map((entry) => entry.trim())
        .find((entry) => entry.startsWith(prefix));

    if (cookieEntry === undefined) {
        return null;
    }

    return cookieEntry.slice(prefix.length);
}

export function csrfToken(): string {
    const xsrfToken = readCookieValue('XSRF-TOKEN');

    if (xsrfToken !== null && xsrfToken !== '') {
        try {
            return decodeURIComponent(xsrfToken);
        } catch {
            return xsrfToken;
        }
    }

    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
        ''
    );
}
