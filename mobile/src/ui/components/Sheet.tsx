import { ReactNode } from 'react';
import { KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { alpha, space, Theme, typography, useStyles, useTheme } from '../theme';
import { ConfirmOutlet } from './Confirm';
import { Icon, IconName } from './Icon';
import { ToastOutlet } from './Toasts';

export interface SheetProps {
    visible: boolean;
    onClose: () => void;
    title: string;
    lede?: string;
    icon?: IconName;
    // A hex colour for the icon badge; the primary gold when left out.
    accent?: string;
    // Replaces the icon, title and lede.
    header?: ReactNode;
    // Pinned under the body; where the sheet's buttons go.
    footer?: ReactNode;
    children: ReactNode;
}

export function Sheet({ visible, onClose, title, lede, icon = 'plus', accent, header, footer, children }: SheetProps) {
    const theme = useTheme();
    const styles = useStyles(makeStyles);
    const insets = useSafeAreaInsets();
    const tint = accent ?? theme.tones.primary.bg;

    return (
        <Modal visible={visible} animationType="slide" presentationStyle="pageSheet" onRequestClose={onClose}>
            <KeyboardAvoidingView
                style={[styles.sheet, { paddingBottom: insets.bottom }]}
                behavior={Platform.OS === 'ios' ? 'padding' : undefined}
            >
                <View style={styles.header}>
                    {header ?? (
                        <>
                            <View style={[styles.avatar, { backgroundColor: alpha(tint, 0.16) }]}>
                                <Icon name={icon} color={tint} />
                            </View>

                            <View style={styles.heading}>
                                <Text style={styles.title} accessibilityRole="header">
                                    {title}
                                </Text>
                                {lede ? <Text style={styles.lede}>{lede}</Text> : null}
                            </View>
                        </>
                    )}

                    <Pressable onPress={onClose} accessibilityRole="button" accessibilityLabel="Close" hitSlop={8} style={styles.close}>
                        <Icon name="x" color={theme.colors.fgMuted} />
                    </Pressable>
                </View>

                <ScrollView style={styles.body} contentContainerStyle={styles.bodyContent} keyboardShouldPersistTaps="handled">
                    {children}
                </ScrollView>

                {footer ? <View style={styles.footer}>{footer}</View> : null}

                <ToastOutlet />
                <ConfirmOutlet />
            </KeyboardAvoidingView>
        </Modal>
    );
}

const makeStyles = (t: Theme) =>
    StyleSheet.create({
        sheet: {
            flex: 1,
            backgroundColor: t.colors.surface,
        },
        header: {
            flexDirection: 'row',
            alignItems: 'center',
            gap: space[8],
            padding: space[16],
            borderBottomWidth: 1,
            borderBottomColor: t.colors.lineStrong,
        },
        avatar: {
            alignItems: 'center',
            justifyContent: 'center',
            width: 40,
            height: 40,
            borderRadius: 20,
        },
        heading: {
            flex: 1,
            minWidth: 0,
        },
        title: {
            ...typography.h3,
            color: t.colors.fg,
        },
        lede: {
            ...typography.body,
            color: t.colors.fgSecondary,
        },
        close: {
            marginLeft: 'auto',
        },
        body: {
            flex: 1,
        },
        bodyContent: {
            padding: space[16],
        },
        footer: {
            padding: space[16],
            borderTopWidth: 1,
            borderTopColor: t.colors.lineStrong,
        },
    });
