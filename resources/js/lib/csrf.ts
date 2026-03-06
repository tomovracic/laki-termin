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

function decodeCookieValue(value: string): string {
    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}

export function csrfHeaders(): Record<string, string> {
    const xsrfToken = readCookieValue('XSRF-TOKEN');

    if (xsrfToken !== null && xsrfToken !== '') {
        return {
            'X-XSRF-TOKEN': decodeCookieValue(xsrfToken),
        };
    }

    const csrfToken =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ??
        '';

    return csrfToken === '' ? {} : { 'X-CSRF-TOKEN': csrfToken };
}
