/** `cn()` joins class names and lets later Tailwind classes override earlier ones (shadcn helper). */
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
