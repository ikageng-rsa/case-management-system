import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Animated, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { mix, palette, radius, space, Theme, typography, useStyles, useTheme } from '../theme';
import { HostStack, useHost, useHostStack } from './hosts';
import { Icon } from './Icon';

export type ToastTone = 'success' | 'danger' | 'warning' | 'info';

export interface ToastOptions {
    message: string;
    tone?: ToastTone;
    // Milliseconds before it goes; left out, a success goes after 5s and a problem stays.
    life?: number | null;
}

interface ToastItem {
    id: number;
    message: string;
    tone: ToastTone;
}

interface ToastContextValue {
    toast: (options: ToastOptions) => void;
    items: ToastItem[];
    dismiss: (id: number) => void;
    hosts: HostStack;
}

const ToastContext = createContext<ToastContextValue | null>(null);

let push: ToastContextValue['toast'] | null = null;

/*
 * A success announces itself and gets out of the way. A problem does not: it
 * stays until it is dismissed, because someone reading an error is usually
 * part-way through fixing the field it is about.
 */
export function ToastProvider({ children }: { children: ReactNode }) {
    const [items, setItems] = useState<ToastItem[]>([]);
    const nextId = useRef(1);
    const hosts = useHostStack();

    const dismiss = useCallback((id: number) => {
        setItems((current) => current.filter((item) => item.id !== id));
    }, []);

    const toast = useCallback(
        ({ message, tone = 'success', life = null }: ToastOptions) => {
            if (!message) {
                return;
            }

            const id = nextId.current++;
            setItems((current) => [...current, { id, message, tone }]);

            const timeout = life ?? (tone === 'success' ? 5000 : null);

            if (timeout) {
                setTimeout(() => dismiss(id), timeout);
            }
        },
        [dismiss],
    );

    useEffect(() => {
        push = toast;

        return () => {
            push = null;
        };
    }, [toast]);

    const value = useMemo(() => ({ toast, items, dismiss, hosts }), [toast, items, dismiss, hosts]);

    return (
        <ToastContext.Provider value={value}>
            {children}
            {hosts.top === null ? <ToastStack items={items} onDismiss={dismiss} /> : null}
        </ToastContext.Provider>
    );
}

export function useToast() {
    const context = useContext(ToastContext);

    if (!context) {
        throw new Error('useToast must be used inside <ToastProvider>.');
    }

    return context.toast;
}

/** For code outside React, such as an API interceptor. */
export function toast(options: ToastOptions) {
    push?.(options);
}

/** Renders the toasts inside an open Sheet; Sheet already does this. */
export function ToastOutlet() {
    const context = useContext(ToastContext);
    const id = useHost(context?.hosts.register);

    if (!context || context.hosts.top !== id) {
        return null;
    }

    return <ToastStack items={context.items} onDismiss={context.dismiss} />;
}

interface ToastStackProps {
    items: ToastItem[];
    onDismiss: (id: number) => void;
}

function ToastStack({ items, onDismiss }: ToastStackProps) {
    const theme = useTheme();
    const styles = useStyles(makeStyles);
    const insets = useSafeAreaInsets();

    return (
        <View pointerEvents="box-none" style={[styles.stack, { top: insets.top + space[12] }]} accessibilityLiveRegion="polite">
            {items.map((item) => {
                const color = theme.tones[item.tone].bg;

                return (
                    <ToastCard key={item.id} item={item} color={color} onDismiss={onDismiss} />
                );
            })}
        </View>
    );
}

interface ToastCardProps {
    item: ToastItem;
    color: string;
    onDismiss: (id: number) => void;
}

function ToastCard({ item, color, onDismiss }: ToastCardProps) {
    const theme = useTheme();
    const styles = useStyles(makeStyles);
    const [progress] = useState(() => new Animated.Value(0));

    useEffect(() => {
        Animated.timing(progress, { toValue: 1, duration: 180, useNativeDriver: true }).start();
    }, [progress]);

    return (
        <Animated.View
            accessibilityRole="alert"
            style={[
                styles.toast,
                {
                    backgroundColor: mix(theme.colors.surface, color, 0.1),
                    borderColor: mix(theme.colors.surface, color, 0.3),
                    opacity: progress,
                    transform: [{ translateY: progress.interpolate({ inputRange: [0, 1], outputRange: [-12, 0] }) }],
                },
            ]}
        >
            <Icon name={item.tone === 'success' ? 'check-circle' : 'alert-triangle'} color={color} />
            <Text style={styles.message}>{item.message}</Text>
            <Pressable onPress={() => onDismiss(item.id)} accessibilityRole="button" accessibilityLabel="Dismiss" hitSlop={8}>
                <Icon name="x" size="sm" color={theme.colors.fgMuted} />
            </Pressable>
        </Animated.View>
    );
}

const makeStyles = (t: Theme) =>
    StyleSheet.create({
        stack: {
            position: 'absolute',
            left: space[12],
            right: space[12],
            zIndex: 1090,
            alignItems: 'flex-end',
            gap: space[8],
        },
        toast: {
            flexDirection: 'row',
            alignItems: 'center',
            gap: space[10],
            width: '100%',
            maxWidth: 420,
            padding: space[12],
            borderWidth: 1,
            borderRadius: radius.base,
            shadowColor: palette.ink900,
            shadowOpacity: 0.12,
            shadowRadius: 12,
            shadowOffset: { width: 0, height: 4 },
            elevation: 4,
        },
        message: {
            ...typography.body,
            flex: 1,
            color: t.colors.fg,
        },
    });
