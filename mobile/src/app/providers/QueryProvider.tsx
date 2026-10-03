import React, {PropsWithChildren} from 'react';
import {Provider} from 'react-redux';
import {store} from '@store/store';

export default function QueryProvider({children}: PropsWithChildren<{}>){
    // Named QueryProvider to match the architecture doc; currently wraps the
    // Redux store. Swap in @tanstack/react-query's QueryClientProvider here
    // (composed with Provider) if/when server-cache concerns outgrow Redux
    // Toolkit's built-in thunks.
    return <Provider store={store}>{children}</Provider>;
}