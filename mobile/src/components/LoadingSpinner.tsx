import React from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { colors } from '@/theme/index';

export default function LoadingSpinner() {
    return (
        <View style={styles.container} accessibilityRole="progressbar" accessibilityLabel="Loading">
            <ActivityIndicator size="large" color={colors.gold} />
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
    },
});
