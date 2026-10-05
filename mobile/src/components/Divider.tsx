import React from 'react';
import { StyleSheet, View, ViewStyle } from 'react-native';
import { colors, spacing} from '@/theme/index';

interface DividerProps {
    readonly ornament?: boolean; // show the samll centered diamond flourish
    readonly style?: ViewStyle;
}

export default function Divider({ornament = false, style}: DividerProps){
    if(!ornament){
        return <View style = {[styles.plain,style]}/>
    }

    return(
        <View style={[styles.ornamentRow, style]}>
            <View style={styles.line}/>
            <View style={styles.diamond}/>
            <View style={styles.line}/>
        </View>
    );
}

const styles = StyleSheet.create({
    plain: {
        height: StyleSheet.hairlineWidth,
        backgroundColor: colors.divider
    },
    ornamentRow:{
        flexDirection: 'row',
        alignItems: 'center',
        marginVertical: spacing.sm
    },
    line:{
        flex: 1,
        height: StyleSheet.hairlineWidth,
        backgroundColor: colors.divider,
    },
    diamond:{
        width: 6,
        height: 6,
        marginHorizontal: spacing.sm,
        backgroundColor: colors.gold,
        transform: [{rotate: '45deg'}]
    }
})