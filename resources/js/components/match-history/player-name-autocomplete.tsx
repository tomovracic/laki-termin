import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import type { MatchHistoryPlayerInput, UserSearchResult } from '@/components/match-history/types';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { csrfHeaders } from '@/lib/csrf';
import { useI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type PlayerNameAutocompleteProps = {
    id: string;
    label?: string;
    value: MatchHistoryPlayerInput;
    onChange: (value: MatchHistoryPlayerInput) => void;
    disabled?: boolean;
    hideLabel?: boolean;
    inputClassName?: string;
    error?: string;
};

function parseGuestName(displayName: string): { firstName: string; lastName: string } {
    const trimmed = displayName.trim();

    if (trimmed === '') {
        return { firstName: '', lastName: '' };
    }

    const spaceIndex = trimmed.indexOf(' ');

    if (spaceIndex === -1) {
        return { firstName: trimmed, lastName: '' };
    }

    return {
        firstName: trimmed.slice(0, spaceIndex).trim(),
        lastName: trimmed.slice(spaceIndex + 1).trim(),
    };
}

export function PlayerNameAutocomplete({
    id,
    label,
    value,
    onChange,
    disabled = false,
    hideLabel = false,
    inputClassName,
    error,
}: PlayerNameAutocompleteProps) {
    const { t } = useI18n();
    const containerRef = useRef<HTMLDivElement>(null);
    const [query, setQuery] = useState(value.display_name);
    const [suggestions, setSuggestions] = useState<UserSearchResult[]>([]);
    const [isOpen, setIsOpen] = useState(false);
    const [isSearching, setIsSearching] = useState(false);

    useEffect(() => {
        setQuery(value.display_name);
    }, [value.display_name]);

    useEffect(() => {
        if (disabled || query.trim().length < 2) {
            setSuggestions([]);
            setIsOpen(false);
            return;
        }

        const timeoutId = window.setTimeout(async () => {
            setIsSearching(true);

            try {
                const response = await fetch(
                    `/users/search?${new URLSearchParams({ q: query.trim() })}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...csrfHeaders(),
                        },
                    },
                );

                if (!response.ok) {
                    setSuggestions([]);
                    return;
                }

                const payload = (await response.json()) as { data: UserSearchResult[] };
                setSuggestions(payload.data ?? []);
                setIsOpen((payload.data ?? []).length > 0);
            } catch {
                setSuggestions([]);
            } finally {
                setIsSearching(false);
            }
        }, 300);

        return () => window.clearTimeout(timeoutId);
    }, [disabled, query]);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);

        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    function handleInputChange(nextQuery: string) {
        setQuery(nextQuery);

        const parsed = parseGuestName(nextQuery);

        onChange({
            user_id: null,
            first_name: parsed.firstName,
            last_name: parsed.lastName,
            display_name: nextQuery,
        });
    }

    function handleSelectSuggestion(user: UserSearchResult) {
        onChange({
            user_id: user.id,
            first_name: user.first_name,
            last_name: user.last_name,
            display_name: user.name,
        });
        setQuery(user.name);
        setIsOpen(false);
    }

    function handleBlur() {
        window.setTimeout(() => {
            setIsOpen(false);

            if (value.user_id !== null) {
                return;
            }

            const parsed = parseGuestName(query);

            onChange({
                user_id: null,
                first_name: parsed.firstName,
                last_name: parsed.lastName,
                display_name: query.trim(),
            });
        }, 150);
    }

    return (
        <div ref={containerRef} className={cn('relative', !hideLabel && 'space-y-2')}>
            {!hideLabel && label !== undefined && <Label htmlFor={id}>{label}</Label>}
            <Input
                id={id}
                value={query}
                onChange={(event) => handleInputChange(event.target.value)}
                onFocus={() => {
                    if (suggestions.length > 0) {
                        setIsOpen(true);
                    }
                }}
                onBlur={handleBlur}
                disabled={disabled}
                placeholder={t('match_history_player_placeholder')}
                autoComplete="off"
                className={inputClassName}
            />
            {isOpen && suggestions.length > 0 && (
                <ul
                    className={cn(
                        'absolute z-50 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-popover p-1 shadow-md',
                    )}
                >
                    {suggestions.map((user) => (
                        <li key={user.id}>
                            <button
                                type="button"
                                className="w-full rounded-sm px-3 py-2 text-left text-sm hover:bg-accent"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => handleSelectSuggestion(user)}
                            >
                                <span className="font-medium">{user.name}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {isSearching && (
                <p className="text-xs text-muted-foreground">{t('match_history_searching')}</p>
            )}
            <InputError message={error} />
        </div>
    );
}
