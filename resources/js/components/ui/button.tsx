import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
    "group/button inline-flex shrink-0 cursor-pointer touch-manipulation items-center justify-center rounded-md border border-transparent bg-clip-padding text-button-14 whitespace-nowrap transition-colors duration-150 ease-geist outline-none select-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-700 disabled:opacity-100 aria-invalid:border-destructive aria-invalid:ring-2 aria-invalid:ring-destructive aria-invalid:ring-offset-2 aria-invalid:ring-offset-background [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                default:
                    "bg-primary text-primary-foreground hover:bg-gray-900 active:bg-gray-800",
                outline:
                    "border-gray-alpha-400 bg-background text-foreground shadow-xs hover:bg-gray-alpha-200 active:bg-gray-alpha-300 aria-expanded:bg-gray-alpha-200",
                secondary:
                    "border-gray-alpha-400 bg-secondary text-secondary-foreground hover:bg-gray-alpha-200 active:bg-gray-alpha-300 aria-expanded:bg-gray-alpha-200",
                ghost: "text-foreground hover:bg-gray-alpha-200 active:bg-gray-alpha-300 aria-expanded:bg-gray-alpha-200",
                destructive:
                    "bg-red-800 text-white hover:bg-red-900 focus-visible:ring-red-700 active:bg-red-700",
                link: "text-primary underline-offset-4 hover:underline",
            },
            size: {
                default:
                    "h-10 gap-2 px-2.5 in-data-[slot=button-group]:rounded-md has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2",
                xs: "h-6 gap-1 rounded-md px-2 text-button-12 in-data-[slot=button-group]:rounded-md has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3",
                sm: "h-8 gap-1 rounded-md px-1.5 in-data-[slot=button-group]:rounded-md has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5",
                lg: "h-12 gap-2 px-3.5 text-button-16 has-data-[icon=inline-end]:pr-3 has-data-[icon=inline-start]:pl-3",
                icon: "size-10",
                "icon-xs":
                    "size-6 rounded-[min(var(--radius-md),8px)] in-data-[slot=button-group]:rounded-md [&_svg:not([class*='size-'])]:size-3",
                "icon-sm":
                    "size-8 rounded-[min(var(--radius-md),10px)] in-data-[slot=button-group]:rounded-md",
                "icon-lg": "size-12",
            },
        },
        defaultVariants: {
            variant: "default",
            size: "default",
        },
    },
)

function Button({
    className,
    variant = "default",
    size = "default",
    asChild = false,
    ...props
}: React.ComponentProps<"button"> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean
    }) {
    const Comp = asChild ? Slot.Root : "button"

    return (
        <Comp
            data-slot="button"
            data-variant={variant}
            data-size={size}
            className={cn(buttonVariants({ variant, size, className }))}
            {...props}
        />
    )
}

export { Button, buttonVariants }
