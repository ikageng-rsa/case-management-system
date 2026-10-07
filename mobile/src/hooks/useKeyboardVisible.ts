import { useEffect, useState } from 'react';
import { Keyboard } from 'react-native';

/**
 * Useful for hiding a floating action button or bottom tab bar chrome while
 * the keyboard is up on forms like AddNarration or NewCase.
 */
export function useKeyboardVisible(): boolean {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const showSub = Keyboard.addListener('keyboardDidShow', () => setVisible(true));
    const hideSub = Keyboard.addListener('keyboardDidHide', () => setVisible(false));
    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, []);

  return visible;
}
