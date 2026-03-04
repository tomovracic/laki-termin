import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type StatCardProps = {
    label: string;
    value: number;
};

export function StatCard({ label, value }: StatCardProps) {
    return (
        <Card className="border-border/70">
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl font-semibold tracking-tight">
                    {value}
                </CardTitle>
            </CardHeader>
        </Card>
    );
}
