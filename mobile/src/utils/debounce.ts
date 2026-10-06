/**
 * Plain debounce for non-component contexts (e.g. debouncing a save-to-cache
 * call from a Redux thunk). For debouncing a value inside a component, use
 * the useDebounce hook instead — it handles cleanup on unmount for you.
 */
export function debounce<Args extends unknown[]>(
  fn: (...args: Args) => void,
  waitMs: number,
): (...args: Args) => void {
  let timeout: ReturnType<typeof setTimeout> | null = null;
  return (...args: Args) => {
    if (timeout) {
      clearTimeout(timeout);
    }
    timeout = setTimeout(() => fn(...args), waitMs);
  };
}
