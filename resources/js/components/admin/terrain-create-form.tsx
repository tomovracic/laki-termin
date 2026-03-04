import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type TerrainCreateFormProps = {
    name: string;
    description: string;
    isSubmitting: boolean;
    errors: Record<string, string[]>;
    onNameChange: (value: string) => void;
    onDescriptionChange: (value: string) => void;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
};

export function TerrainCreateForm({
    name,
    description,
    isSubmitting,
    errors,
    onNameChange,
    onDescriptionChange,
    onSubmit,
}: TerrainCreateFormProps) {
    return (
        <Card className="border-border/70">
            <CardHeader>
                <CardTitle>Create terrain</CardTitle>
                <CardDescription>
                    Add a new terrain using a clear name and optional description.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form className="space-y-4" onSubmit={onSubmit}>
                    <div className="grid gap-2">
                        <Label htmlFor="terrain-name">Name</Label>
                        <Input
                            id="terrain-name"
                            required
                            value={name}
                            onChange={(event) => onNameChange(event.target.value)}
                            placeholder="e.g. Court A"
                        />
                        {errors.name?.[0] !== undefined && (
                            <p className="text-sm text-red-500">{errors.name[0]}</p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="terrain-description">Description</Label>
                        <textarea
                            id="terrain-description"
                            className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-24 rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                            value={description}
                            onChange={(event) => onDescriptionChange(event.target.value)}
                            placeholder="Optional details"
                        />
                    </div>

                    <Button type="submit" disabled={isSubmitting} className="w-full">
                        {isSubmitting ? 'Creating...' : 'Create terrain'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
