import { useEffect, useRef, useState } from 'react';
import { Pressable, StyleProp, StyleSheet, Text, TextInput, View, ViewStyle } from 'react-native';
import { space, Theme, typography, useStyles, useTheme } from '../theme';
import { Button } from './Button';
import { Field, useFieldStyles } from './Field';

export interface MatterOption {
    id: number | string;
    reference: string;
    title: string;
    client: string;
    type: string;
    year: number | string;
}

export interface MatterPickerProps {
    value: MatterOption | null;
    onChange: (matter: MatterOption | null) => void;
    // Asked with what has been typed (empty on focus); answers with the best few matches.
    search: (query: string) => Promise<MatterOption[]>;
    label?: string;
    placeholder?: string;
    hint?: string;
    error?: string;
    containerStyle?: StyleProp<ViewStyle>;
}

/*
 * Pick one matter out of any number. Type a few words — a year, a type, part of
 * a reference, a client — and the best matches are offered.
 */
export function MatterPicker({
    value,
    onChange,
    search,
    label,
    placeholder = 'Type a year, a type, a reference or a client…',
    hint,
    error,
    containerStyle,
}: MatterPickerProps) {
    const theme = useTheme();
    const field = useFieldStyles();
    const styles = useStyles(makeStyles);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<MatterOption[]>([]);
    const [open, setOpen] = useState(false);
    const [searched, setSearched] = useState(false);
    const [focused, setFocused] = useState(false);
    const lookups = useRef(0);
    const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);
    const input = useRef<React.ComponentRef<typeof TextInput>>(null);
    const invalid = Boolean(error);

    useEffect(
        () => () => {
            if (debounce.current) clearTimeout(debounce.current);
        },
        [],
    );

    async function run(term: string) {
        const lookup = ++lookups.current;

        try {
            const found = await search(term);

            // Only the latest search may answer.
            if (lookup !== lookups.current) {
                return;
            }

            setResults(found);
            setSearched(true);
        } catch {
            if (lookup === lookups.current) setResults([]);
        }
    }

    function handleChange(text: string) {
        setQuery(text);
        setOpen(true);

        if (debounce.current) clearTimeout(debounce.current);
        debounce.current = setTimeout(() => run(text), 250);
    }

    function pick(matter: MatterOption) {
        onChange(matter);
        setQuery('');
        setResults([]);
        setOpen(false);
        setSearched(false);
    }

    function clear() {
        onChange(null);
        requestAnimationFrame(() => input.current?.focus());
    }

    return (
        <Field label={label} hint={hint} error={error} style={containerStyle}>
            {value ? (
                <View style={[field.box, styles.selected, invalid && field.invalid]}>
                    <View style={styles.summary}>
                        <MatterLines matter={value} />
                    </View>
                    <Button variant="link" size="sm" onPress={clear}>
                        Change
                    </Button>
                </View>
            ) : (
                <>
                    <TextInput
                        ref={input}
                        accessibilityLabel={label}
                        value={query}
                        onChangeText={handleChange}
                        onFocus={() => {
                            setFocused(true);
                            setOpen(true);
                            run(query);
                        }}
                        onBlur={() => setFocused(false)}
                        onSubmitEditing={() => results[0] && pick(results[0])}
                        placeholder={placeholder}
                        placeholderTextColor={theme.colors.fgMuted}
                        selectionColor={theme.colors.accent}
                        autoCorrect={false}
                        autoCapitalize="none"
                        returnKeyType="search"
                        style={[field.box, field.text, focused && field.focused, invalid && field.invalid]}
                    />

                    {open && results.length > 0 ? (
                        <View style={styles.results} accessibilityRole="list">
                            {results.map((result) => (
                                <Pressable
                                    key={String(result.id)}
                                    accessibilityRole="button"
                                    onPress={() => pick(result)}
                                    style={({ pressed }) => [styles.result, pressed && styles.resultPressed]}
                                >
                                    <MatterLines matter={result} />
                                </Pressable>
                            ))}
                        </View>
                    ) : null}

                    {open && searched && results.length === 0 ? <Text style={styles.empty}>No matter fits that.</Text> : null}
                </>
            )}
        </Field>
    );
}

function MatterLines({ matter }: { matter: MatterOption }) {
    const styles = useStyles(makeStyles);

    return (
        <>
            <Text style={styles.title} numberOfLines={1}>
                <Text style={styles.meta}>{matter.reference}</Text> {matter.title}
            </Text>
            <Text style={styles.meta} numberOfLines={1}>
                {`${matter.client} · ${matter.type} · ${matter.year}`}
            </Text>
        </>
    );
}

const makeStyles = (t: Theme) =>
    StyleSheet.create({
        selected: {
            flexDirection: 'row',
            alignItems: 'center',
            justifyContent: 'space-between',
            gap: space[8],
        },
        summary: {
            flex: 1,
            minWidth: 0,
        },
        results: {
            marginTop: space[8],
            borderTopWidth: 1,
            borderTopColor: t.colors.line,
        },
        result: {
            paddingVertical: space[8],
            paddingHorizontal: space[12],
            borderBottomWidth: 1,
            borderBottomColor: t.colors.line,
        },
        resultPressed: {
            backgroundColor: t.colors.surfaceRaised,
        },
        title: {
            ...typography.body,
            color: t.colors.fg,
        },
        meta: {
            ...typography.meta,
            color: t.colors.fgMuted,
        },
        empty: {
            ...typography.body,
            color: t.colors.fgSecondary,
            marginTop: space[8],
        },
    });
