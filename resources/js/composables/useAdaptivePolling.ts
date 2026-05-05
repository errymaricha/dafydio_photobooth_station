import { onBeforeUnmount, onMounted, ref } from 'vue';

type PollingInterval = number | (() => number);

type AdaptivePollingOptions = {
    activeIntervalMs: PollingInterval;
    idleIntervalMs?: PollingInterval;
    idleAfterMs?: number;
    autoStart?: boolean;
};

const resolveInterval = (interval: PollingInterval): number => {
    return typeof interval === 'function' ? interval() : interval;
};

export function useAdaptivePolling(
    callback: () => Promise<void> | void,
    options: AdaptivePollingOptions,
) {
    const isRunning = ref(false);
    const isPaused = ref(false);
    const lastInteractionAt = ref(Date.now());
    let timer: number | null = null;

    const clearTimer = (): void => {
        if (timer !== null) {
            window.clearTimeout(timer);
            timer = null;
        }
    };

    const currentInterval = (): number => {
        const idleAfterMs = options.idleAfterMs ?? 60_000;
        const idleIntervalMs =
            options.idleIntervalMs ?? options.activeIntervalMs;
        const hasBeenIdle = Date.now() - lastInteractionAt.value >= idleAfterMs;

        return Math.max(
            1_000,
            resolveInterval(hasBeenIdle ? idleIntervalMs : options.activeIntervalMs),
        );
    };

    const schedule = (): void => {
        clearTimer();

        if (!isRunning.value || document.visibilityState === 'hidden') {
            isPaused.value = document.visibilityState === 'hidden';

            return;
        }

        isPaused.value = false;
        timer = window.setTimeout(async () => {
            try {
                await callback();
            } finally {
                schedule();
            }
        }, currentInterval());
    };

    const markInteraction = (): void => {
        lastInteractionAt.value = Date.now();
    };

    const handleVisibilityChange = (): void => {
        if (document.visibilityState === 'hidden') {
            clearTimer();
            isPaused.value = true;

            return;
        }

        markInteraction();
        schedule();
    };

    const start = (): void => {
        isRunning.value = true;
        schedule();
    };

    const stop = (): void => {
        isRunning.value = false;
        isPaused.value = false;
        clearTimer();
    };

    onMounted(() => {
        window.addEventListener('click', markInteraction, { passive: true });
        window.addEventListener('keydown', markInteraction);
        window.addEventListener('scroll', markInteraction, { passive: true });
        window.addEventListener('touchstart', markInteraction, { passive: true });
        document.addEventListener('visibilitychange', handleVisibilityChange);

        if (options.autoStart ?? true) {
            start();
        }
    });

    onBeforeUnmount(() => {
        stop();
        window.removeEventListener('click', markInteraction);
        window.removeEventListener('keydown', markInteraction);
        window.removeEventListener('scroll', markInteraction);
        window.removeEventListener('touchstart', markInteraction);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
    });

    return {
        isPaused,
        isRunning,
        start,
        stop,
    };
}
