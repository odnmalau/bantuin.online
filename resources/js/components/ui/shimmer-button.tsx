import React, { type CSSProperties } from 'react';
import type { VariantProps } from 'class-variance-authority';

import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface ShimmerButtonProps
    extends React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {
    shimmerColor?: string;
    shimmerSize?: string;
    borderRadius?: string;
    shimmerDuration?: string;
    background?: string;
    className?: string;
    children?: React.ReactNode;
}

const ShimmerButton = React.forwardRef<
    HTMLButtonElement,
    ShimmerButtonProps
>(
    (
        {
            shimmerColor = 'var(--foreground)',
            shimmerSize = '0.05em',
            shimmerDuration = '3s',
            borderRadius = 'var(--radius)',
            background = 'var(--background)',
            variant = 'default',
            size = 'default',
            className,
            children,
            ...props
        },
        ref,
    ) => {
        return (
            <button
                style={
                    {
                        '--spread': '90deg',
                        '--shimmer-color': shimmerColor,
                        '--radius': borderRadius,
                        '--speed': shimmerDuration,
                        '--cut': shimmerSize,
                        '--bg': background,
                    } as CSSProperties
                }
                className={cn(
                    buttonVariants({ variant, size }),
                    'group relative z-0 overflow-hidden',
                    className,
                )}
                ref={ref}
                {...props}
            >
                <div
                    className={cn(
                        '-z-30 blur-[2px]',
                        'absolute inset-0 overflow-visible [container-type:size]',
                    )}
                >
                    <div className="absolute inset-0 h-[100cqh] animate-shimmer-slide [aspect-ratio:1] [border-radius:0] [mask:none]">
                        <div className="absolute -inset-full w-auto animate-spin-around rotate-0 [background:conic-gradient(from_calc(270deg-(var(--spread)*0.5)),transparent_0,var(--shimmer-color)_var(--spread),transparent_var(--spread))] [translate:0_0]" />
                    </div>
                </div>

                {children}

                <div
                    className={cn(
                        'absolute inset-0 size-full',
                        'rounded-[inherit] shadow-[inset_0_-4px_8px_color-mix(in_oklab,currentColor_8%,transparent)]',
                        'transform-gpu transition-all duration-300 ease-in-out',
                        'group-hover:shadow-[inset_0_-4px_8px_color-mix(in_oklab,currentColor_12%,transparent)]',
                        'group-active:shadow-[inset_0_-5px_8px_color-mix(in_oklab,currentColor_16%,transparent)]',
                    )}
                />

                <div
                    className={cn(
                        'absolute -z-20 [background:var(--bg)] [border-radius:var(--radius)] [inset:var(--cut)]',
                    )}
                />
            </button>
        );
    },
);

ShimmerButton.displayName = 'ShimmerButton';

export { ShimmerButton };
