import { Skeleton } from "@/components/ui/skeleton";

export const ToolCardSkeleton = () => {
  return (
    <div className="group relative bg-card border border-border rounded-xl p-5 animate-pulse">
      <div className="flex items-start gap-4">
        <Skeleton className="w-11 h-11 rounded-lg shrink-0" />
        <div className="flex-1 space-y-2">
          <Skeleton className="h-5 w-3/4" />
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-2/3" />
        </div>
      </div>
    </div>
  );
};

export const ToolGridSkeleton = ({ count = 6 }: { count?: number }) => {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      {Array.from({ length: count }).map((_, i) => (
        <ToolCardSkeleton key={i} />
      ))}
    </div>
  );
};
