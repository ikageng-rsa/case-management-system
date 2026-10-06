import { useEffect, useState } from 'react';

/**
 * Debounces a fast-changing value (typically a search TextInput's value) so
 * callers can useEffect on the debounced result instead of firing an API
 * call on every keystroke. e.g. Case search-by-client-name.
 */
export function useDebounce<T>(value: T, delayMs = 350): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timeout = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timeout);
  }, [value, delayMs]);

  return debounced;
}
