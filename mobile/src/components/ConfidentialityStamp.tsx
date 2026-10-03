import React from 'react';
import { StyleSheet, Text, View, ViewStyle } from 'react-native';
import { colors, radius, typography} from '@/theme/index';

export type ConfidentialityLevel = 'Standard' | 'Restricted' | 'Privileged';

const STAMP_STYLE: Record<ConfidentialityLevel, {color: string, tint:string}> = {
    Standard: {color: colors.green, tint: colors.greenTint},
    Restricted: {color: colors.gold, tint: colors.goldTint},
    Privileged: {color: colors.burgundy, tint: colors.burgundyTint}
};

interface ConfidentialityStampProps{
    level: ConfidentialityLevel,
    style?: ViewStyle;
}

/**Visually reads as an ink stamp - slight rotation, bordered, tracked-out label */
export default function confidentialityStamp({level, style}: ConfidentialityStampProps){
    const {color,tint} = STAMP_STYLE[level];
    return(
        <View
        style = {[
            styles.stamp,
            { borderColor: color, backgroundColor:tint, transform: [{rotate: '-3deg'}]}
        ]}
        >
            <Text style={[styles.label, {color}]}>{level.toUpperCase()}</Text>
        </View>
    )
}

const Styles = StyleSheet.create({
    stamp:{
        borderWidth: 1.5,
        borderRadius: radius.sm,
        paddingHorizontal: 8,
        paddingVertical: 2,
        alignSelf: 'flex-start'
    },
    label:{
        ...typography.overline,
        fontSize: 10
    }
})