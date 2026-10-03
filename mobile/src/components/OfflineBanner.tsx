import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import {useNetInfo} from '@/hooks/useNetInfo';
import {colors, spacing, typography} from '@/theme/index';

/**
 * Sits above the navigator (mounted once in App.tsx) so any screen - most
 * importantly Narration/Darzartion entry forms used in the field - has a 
 * persistent, unmissable cue that submissions will queue rather than fail
 * silently. Renders nothing while online
 */

export default function OfflineBanner(){
    const {isOnline} = useNetInfo;

    if(isOnline){
        return null;
    }

    return(
        <View style={styles.banner} testID='offline-banner'>
            <Text style={styles.text}>You're offline - entries will sync once you're back online. </Text>
        </View>
    );
}

const styles = StyleSheet.create({
    banner:{
        backgroundColor: colors.gold,
        paddingVertical: spacing.xs,
        paddingHorizontal: spacing.md
    },
    text: {
        ...typography.captionBold,
        color: colors.navyDark,
        textAlign: 'center'
    }
})