import { Search } from 'lucide-react';
import { Input } from '@/components/ui/input';

type SearchInputProps = {
    value: string;
    placeholder: string;
    onChange: (value: string) => void;
};

export function SearchInput({ value, placeholder, onChange }: SearchInputProps) {
    return (
        <div className="relative">
            <Search className="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
            <Input
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                className="pl-9"
            />
        </div>
    );
}
