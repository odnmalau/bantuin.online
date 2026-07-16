import * as React from "react"

import { cn } from "@/lib/utils"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
    const { onKeyDown, ...textareaProps } = props

    return (
        <textarea
            data-slot="textarea"
            className={cn(
                "flex field-sizing-content min-h-16 w-full touch-manipulation rounded-md border border-input bg-background px-3 py-2 text-copy-16 shadow-xs transition-[color,box-shadow] duration-150 ease-geist outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-700 disabled:opacity-100 aria-invalid:border-destructive aria-invalid:ring-2 aria-invalid:ring-destructive sm:text-copy-14",
                className,
            )}
            onKeyDown={(event) => {
                onKeyDown?.(event)

                if (
                    !event.defaultPrevented &&
                    event.key === "Enter" &&
                    (event.metaKey || event.ctrlKey)
                ) {
                    event.preventDefault()
                    event.currentTarget.form?.requestSubmit()
                }
            }}
            {...textareaProps}
        />
    )
}

export { Textarea }
