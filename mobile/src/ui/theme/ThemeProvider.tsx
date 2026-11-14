import { createContext, ReactNode, useContext, useMemo } from 'react';
import { useColorScheme } from 'react-native';
import { darkTheme, lightTheme, Theme, ThemeMode } from './themes';

const ThemeContext = createContext<Theme>(lightTheme);

interface ThemeProviderProps {
    mode?: ThemeMode | 'system';
    children: ReactNode;
}

export function ThemeProvider({ mode = 'system', children }: ThemeProviderProps) {
    const scheme = useColorScheme();
    const resolved = mode === 'system' ? (scheme === 'dark' ? 'dark' : 'light') : mode;

    return (
        <ThemeContext.Provider value={resolved === 'dark' ? darkTheme : lightTheme}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme(): Theme {
    return useContext(ThemeContext);
}

/** Builds a component's StyleSheet from the current theme, once per theme. */
export function useStyles<T>(factory: (theme: Theme) => T): T {
    const theme = useTheme();

    return useMemo(() => factory(theme), [factory, theme]);
}
