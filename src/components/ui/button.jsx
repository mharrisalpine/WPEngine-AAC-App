import { cn } from '@/lib/utils';
import { Slot } from '@radix-ui/react-slot';
import { cva } from 'class-variance-authority';
import React from 'react';

const buttonVariants = cva(
	'inline-flex items-center justify-center rounded-none text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
	{
		variants: {
			variant: {
				default: 'bg-[#8f1515] text-white hover:bg-[#6b1010]',
				destructive:
          'bg-[#6b1010] text-white hover:bg-[#530c0c]',
				outline:
          'border border-stone-300 bg-transparent text-stone-800 hover:bg-stone-100 hover:text-stone-900',
				secondary:
          'bg-[#f8c235] text-black hover:bg-[#dda914]',
				ghost: 'text-stone-800 hover:bg-stone-100 hover:text-[#8f1515]',
				link: 'text-[#8f1515] underline-offset-4 hover:underline',
			},
			size: {
				default: 'h-10 px-4 py-2',
				sm: 'h-9 px-3',
				lg: 'h-11 px-8',
				icon: 'h-10 w-10',
			},
		},
		defaultVariants: {
			variant: 'default',
			size: 'default',
		},
	},
);

const Button = React.forwardRef(({ className, variant, size, asChild = false, ...props }, ref) => {
	const Comp = asChild ? Slot : 'button';
	return (
		<Comp
			className={cn(buttonVariants({ variant, size, className }))}
			data-aac-button="true"
			ref={ref}
			{...props}
		/>
	);
});
Button.displayName = 'Button';

export { Button, buttonVariants };
