import * as React from "react"

import { cn } from "@/lib/utils"

function Input({
    className,
    type,
    size = "default",
    ...props
}: Omit<React.ComponentProps<"input">, "size"> & {
    size?: "sm" | "default" | "lg"
}) {
    return (
        <input
            type={type}
            data-slot="input"
            data-size={size}
            className={cn(
                "w-full min-w-0 touch-manipulation rounded-md border border-input bg-background px-3 py-1 text-label-16 shadow-xs transition-[color,box-shadow] duration-150 ease-geist outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-button-14 file:text-foreground placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-700 disabled:opacity-100 aria-invalid:border-destructive aria-invalid:ring-2 aria-invalid:ring-destructive data-[size=default]:h-10 data-[size=lg]:h-12 data-[size=lg]:text-label-16 data-[size=sm]:h-8 sm:text-label-14",
                className,
            )}
            {...props}
        />
    )
}

export { Input }
