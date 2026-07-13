import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export function PaginationControls({
    paginator,
    only,
}: {
    paginator: Pick<
        Paginated<unknown>,
        'current_page' | 'last_page' | 'total' | 'prev_page_url' | 'next_page_url'
    >;
    only?: string[];
}) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-3">
            <p className="text-sm text-muted-foreground">
                Page {paginator.current_page} of {paginator.last_page}
                {paginator.total > 0 ? ` · ${paginator.total} total` : ''}
            </p>
            <div className="flex gap-2">
                {paginator.prev_page_url ? (
                    <Button asChild size="sm" variant="outline">
                        <Link
                            href={paginator.prev_page_url}
                            only={only}
                            preserveScroll
                            preserveState
                        >
                            Previous
                        </Link>
                    </Button>
                ) : (
                    <Button size="sm" variant="outline" disabled>
                        Previous
                    </Button>
                )}
                {paginator.next_page_url ? (
                    <Button asChild size="sm" variant="outline">
                        <Link
                            href={paginator.next_page_url}
                            only={only}
                            preserveScroll
                            preserveState
                        >
                            Next
                        </Link>
                    </Button>
                ) : (
                    <Button size="sm" variant="outline" disabled>
                        Next
                    </Button>
                )}
            </div>
        </div>
    );
}
