import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { fontFamily, fontSize, radius, space, Theme, typography, useStyles, useTheme } from '../theme';
import { Button } from './Button';
import { useFieldStyles } from './Field';
import { HostStack, useHost, useHostStack } from './hosts';
import { Icon, IconName } from './Icon';

export interface ConfirmOptions {
    title?: string;
    message?: string;
    actionLabel?: string;
    cancelLabel?: string;
    tone?: 'danger' | 'primary';
    icon?: IconName;
    // Given, the dialogue asks for a reason and answers with it.
    promptLabel?: string;
}

export type ConfirmAnswer = boolean | { reason: string };

interface ConfirmContextValue {
    confirm: (options?: ConfirmOptions) => Promise<ConfirmAnswer>;
    pending: ConfirmOptions | null;
    settle: (answer: ConfirmAnswer) => void;
    hosts: HostStack;
}

const ConfirmContext = createContext<ConfirmContextValue | null>(null);

let ask: ConfirmContextValue['confirm'] | null = null;

export function ConfirmProvider({ children }: { children: ReactNode }) {
    const [pending, setPending] = useState<ConfirmOptions | null>(null);
    const resolver = useRef<((answer: ConfirmAnswer) => void) | null>(null);
    const hosts = useHostStack();

    const settle = useCallback((answer: ConfirmAnswer) => {
        resolver.current?.(answer);
        resolver.current = null;
        setPending(null);
    }, []);

    const confirm = useCallback((options: ConfirmOptions = {}) => {
        // A question still waiting is withdrawn rather than left hanging.
        resolver.current?.(false);

        return new Promise<ConfirmAnswer>((resolve) => {
            resolver.current = resolve;
            setPending(options);
        });
    }, []);

    useEffect(() => {
        ask = confirm;

        return () => {
            ask = null;
        };
    }, [confirm]);

    const value = useMemo(() => ({ confirm, pending, settle, hosts }), [confirm, pending, settle, hosts]);

    return (
        <ConfirmContext.Provider value={value}>
            {children}
            {hosts.top === null ? <ConfirmDialog pending={pending} onSettle={settle} /> : null}
        </ConfirmContext.Provider>
    );
}

export function useConfirm() {
    const context = useContext(ConfirmContext);

    if (!context) {
        throw new Error('useConfirm must be used inside <ConfirmProvider>.');
    }

    return context.confirm;
}

/** For code outside React, such as an API interceptor. */
export function confirmAction(options: ConfirmOptions = {}): Promise<ConfirmAnswer> {
    return ask ? ask(options) : Promise.resolve(false);
}

/** Renders the dialogue inside an open Sheet; Sheet already does this. */
export function ConfirmOutlet() {
    const context = useContext(ConfirmContext);
    const id = useHost(context?.hosts.register);

    if (!context || context.hosts.top !== id) {
        return null;
    }

    return <ConfirmDialog pending={context.pending} onSettle={context.settle} />;
}

interface ConfirmDialogProps {
    pending: ConfirmOptions | null;
    onSettle: (answer: ConfirmAnswer) => void;
}

function ConfirmDialog({ pending, onSettle }: ConfirmDialogProps) {
    const theme = useTheme();
    const field = useFieldStyles();
    const styles = useStyles(makeStyles);
    const [reason, setReason] = useState('');
    const [seen, setSeen] = useState(pending);

    // A new question starts with an empty reason (state adjusted during render, not in an effect).
    if (seen !== pending) {
        setSeen(pending);
        setReason('');
    }

    const tone = pending?.tone ?? 'danger';
    const icon = pending?.icon ?? (tone === 'danger' ? 'alert-triangle' : 'help-circle');
    const promptLabel = pending?.promptLabel ?? null;
    const canProceed = promptLabel === null || reason.trim() !== '';

    const proceed = () => {
        if (!canProceed) {
            return;
        }

        onSettle(promptLabel === null ? true : { reason: reason.trim() });
    };

    const cancel = () => onSettle(false);

    return (
        <Modal visible={pending !== null} transparent animationType="fade" statusBarTranslucent onRequestClose={cancel}>
            <View style={styles.overlay}>
                <Pressable style={styles.backdrop} onPress={cancel} accessibilityLabel="Cancel" />

                <View style={styles.dialog} accessibilityRole="alert" accessibilityLabel={pending?.title}>
                    <View style={styles.body}>
                        <View style={[styles.avatar, { backgroundColor: theme.tones[tone].tint }]}>
                            <Icon name={icon} color={theme.tones[tone].bg} />
                        </View>

                        <Text style={styles.title}>{pending?.title ?? 'Are you sure?'}</Text>
                        {pending?.message ? <Text style={styles.message}>{pending.message}</Text> : null}

                        {promptLabel ? (
                            <View style={styles.prompt}>
                                <Text style={styles.promptLabel}>{promptLabel}</Text>
                                <TextInput
                                    accessibilityLabel={promptLabel}
                                    value={reason}
                                    onChangeText={setReason}
                                    multiline
                                    numberOfLines={2}
                                    autoFocus
                                    selectionColor={theme.colors.accent}
                                    style={[field.box, field.text, styles.reason]}
                                />
                            </View>
                        ) : null}
                    </View>

                    <View style={styles.footer}>
                        <Button variant="outline-secondary" onPress={cancel}>
                            {pending?.cancelLabel ?? 'Cancel'}
                        </Button>
                        <Button variant={tone} disabled={!canProceed} onPress={proceed}>
                            {pending?.actionLabel ?? 'Confirm'}
                        </Button>
                    </View>
                </View>
            </View>
        </Modal>
    );
}

const makeStyles = (t: Theme) =>
    StyleSheet.create({
        overlay: {
            flex: 1,
            justifyContent: 'center',
            padding: space[16],
        },
        backdrop: {
            ...StyleSheet.absoluteFill,
            backgroundColor: t.colors.backdrop,
        },
        dialog: {
            width: '100%',
            maxWidth: 448,
            alignSelf: 'center',
            backgroundColor: t.colors.surface,
            borderRadius: radius.lg,
        },
        body: {
            padding: space[24],
            paddingBottom: space[16],
        },
        avatar: {
            alignItems: 'center',
            justifyContent: 'center',
            width: 40,
            height: 40,
            marginBottom: space[16],
            borderRadius: radius.sm,
        },
        title: {
            color: t.colors.fg,
            fontFamily: fontFamily.sansSemibold,
            fontSize: fontSize.displaySm,
            lineHeight: 28,
            marginBottom: space[4],
        },
        message: {
            ...typography.body,
            color: t.colors.fgSecondary,
        },
        prompt: {
            marginTop: space[16],
        },
        promptLabel: {
            ...typography.body,
            color: t.colors.fgSecondary,
            marginBottom: space[8],
        },
        reason: {
            minHeight: 64,
            textAlignVertical: 'top',
        },
        footer: {
            flexDirection: 'row',
            justifyContent: 'flex-end',
            gap: space[8],
            padding: space[24],
            paddingTop: space[8],
        },
    });
