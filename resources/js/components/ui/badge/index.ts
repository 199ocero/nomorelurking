import { cva, type VariantProps } from 'class-variance-authority'

export { default as Badge } from './Badge.vue'

export const badgeVariants = cva(
  'inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden',
  {
    variants: {
      variant: {
        default:
          'border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90',
        secondary:
          'border-transparent bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90',
        destructive:
         'border-transparent bg-destructive text-white [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60',
        outline:
          'text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground',
          success: `
          border border-green-600/20
          bg-green-500/90
          text-white
          dark:bg-green-400/90
          dark:text-green-950
          shadow-sm
          ring-1 ring-inset ring-green-600/10
          hover:bg-green-600 hover:text-white
          dark:hover:bg-green-300 dark:hover:text-green-950
        `,
        
        warning: `
          border border-amber-600/20
          bg-amber-500/90
          text-white
          dark:bg-amber-400/90
          dark:text-amber-950
          shadow-sm
          ring-1 ring-inset ring-amber-600/10
          hover:bg-amber-600 hover:text-white
          dark:hover:bg-amber-300 dark:hover:text-amber-950
        `,
        
        info: `
          border border-blue-600/20
          bg-blue-500/90
          text-white
          dark:bg-blue-400/90
          dark:text-blue-950
          shadow-sm
          ring-1 ring-inset ring-blue-600/10
          hover:bg-blue-600 hover:text-white
          dark:hover:bg-blue-300 dark:hover:text-blue-950
        `,
        
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>
