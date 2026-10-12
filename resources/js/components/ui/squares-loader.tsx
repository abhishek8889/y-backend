import { cn } from '@/lib/utils';

type SquaresLoaderProps = {
    label?: string;
    className?: string;
    screen?: boolean;
};

/**
 * Dashboard squares loader — three black squares pulsing left-to-right.
 */
function SquaresLoader({
    label = 'LOADING',
    className,
    screen = false,
}: SquaresLoaderProps) {
    return (
        <div
            className={cn(
                'squares-loader',
                screen && 'squares-loader--screen',
                className,
            )}
            role="status"
            aria-live="polite"
            aria-label={label ?? 'Loading'}
        >
            <div className="squares-loader__dots">
                <span />
                <span />
                <span />
            </div>
            {label ? <p className="squares-loader__label">{label}</p> : null}
        </div>
    );
}

export { SquaresLoader };
