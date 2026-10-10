import React from 'react';
import { ActivityIndicator, StyleSheet,View } from 'react-native';
import {colors} from '@/theme/index';

export default function LoadingSpinnner(){
    return(
        <View style={styles.container}>
            <ActivityIndicator size="large" color={colors.navy}/>
        </View>
    );
}

const styles = StyleSheet.create({
    container:{
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center'
    }
})