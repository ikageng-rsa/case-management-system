import { useCallback, useEffect, useRef, useState } from 'react';
import { StyleProp, StyleSheet, Text, View, ViewStyle } from 'react-native';
import { fontFamily, fontSize, space, Theme, useStyles } from '../theme';
import { Button } from './Button';
import { toast } from './Toasts';

export interface RevealValueProps {
    masked: string;
    // Asks the server for the value, which checks who may see it and records that it was read.
    reveal: () => Promise<string>;
    seconds?: number;
    style?: StyleProp<ViewStyle>;
}

/*
 * A masked value that can be shown in full, briefly. It is held for a few
 * seconds and then dropped, so it does not linger on the screen.
 */
export function RevealValue({ masked, reveal, seconds = 10, style }: RevealValueProps) {
    const styles = useStyles(makeStyles);
    const [value, setValue] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const hide = useCallback(() => {
        if (timer.current) clearTimeout(timer.current);
        timer.current = null;
        setValue(null);
    }, []);

    useEffect(
        () => () => {
            if (timer.current) clearTimeout(timer.current);
        },
        [],
    );

    async function toggle() {
        if (value !== null) {
            hide();

            return;
        }

        setLoading(true);

        try {
            setValue(await reveal());
            timer.current = setTimeout(hide, seconds * 1000);
        } catch {
            toast({ message: 'That could not be shown.', tone: 'danger' });
        } finally {
            setLoading(false);
        }
    }

    return (
        <View style={[styles.row, style]}>
            <Text style={styles.value} selectable={value !== null}>
                {value ?? masked}
            </Text>
            <Button variant="link" size="sm" icon={value === null ? 'eye' : 'eye-off'} loading={loading} onPress={toggle}>
                {value === null ? 'Show' : 'Hide'}
            </Button>
        </View>
    );
}

const makeStyles = (t: Theme) =>
    StyleSheet.create({
        row: {
            flexDirection: 'row',
            alignItems: 'center',
            gap: space[8],
        },
        value: {
            color: t.colors.fg,
            fontFamily: fontFamily.mono,
            fontSize: fontSize.md,
        },
    });
