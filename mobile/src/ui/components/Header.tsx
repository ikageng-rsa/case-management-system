import { ReactNode } from 'react';
import { StyleProp, StyleSheet, View, ViewStyle } from 'react-native';
import { layout, space, Theme, useStyles } from '../theme';
import { SearchField, SearchFieldProps } from './SearchField';

export interface HeaderProps {
    search?: boolean;
    searchProps?: SearchFieldProps;
    leading?: ReactNode;
    children?: ReactNode;
    style?: StyleProp<ViewStyle>;
}

export function Header({ search = true, searchProps, leading, children, style }: HeaderProps) {
    const styles = useStyles(makeStyles);

    return (
        <View style={[styles.header, style]}>
            {leading}

            {search ? (
                <SearchField
                    placeholder="Search clients, references, narrations…"
                    {...searchProps}
                    containerStyle={[styles.search, searchProps?.containerStyle]}
                />
            ) : null}

            <View style={styles.actions}>{children}</View>
        </View>
    );
}

const makeStyles = (t: Theme) =>
    StyleSheet.create({
        header: {
            flexDirection: 'row',
            alignItems: 'center',
            gap: space[12],
            minHeight: layout.topbarHeight,
            paddingHorizontal: space[16],
            backgroundColor: t.colors.surface,
            borderBottomWidth: 1,
            borderBottomColor: t.colors.lineStrong,
        },
        search: {
            flex: 1,
        },
        actions: {
            flexDirection: 'row',
            alignItems: 'center',
            gap: space[10],
            marginLeft: 'auto',
        },
    });
