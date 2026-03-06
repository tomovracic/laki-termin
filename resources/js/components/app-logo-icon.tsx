import { useId } from 'react';
import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    const gradientId = useId().replace(/:/g, '');

    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id={gradientId} x1="6" y1="4" x2="34" y2="36" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stopColor="#0EA5E9" />
                    <stop offset="52%" stopColor="#6366F1" />
                    <stop offset="100%" stopColor="#8B5CF6" />
                </linearGradient>
            </defs>

            <rect x="0" y="0" width="40" height="40" rx="10" fill={`url(#${gradientId})`} />
            <path
                d="M10 14.75C10 13.78 10.78 13 11.75 13C12.72 13 13.5 13.78 13.5 14.75V24H18.5C19.47 24 20.25 24.78 20.25 25.75C20.25 26.72 19.47 27.5 18.5 27.5H11.75C10.78 27.5 10 26.72 10 25.75V14.75Z"
                fill="white"
            />
            <path
                d="M20.75 14.75C20.75 13.78 21.53 13 22.5 13H31.25C32.22 13 33 13.78 33 14.75C33 15.72 32.22 16.5 31.25 16.5H28.75V25.75C28.75 26.72 27.97 27.5 27 27.5C26.03 27.5 25.25 26.72 25.25 25.75V16.5H22.5C21.53 16.5 20.75 15.72 20.75 14.75Z"
                fill="white"
            />
        </svg>
    );
}
