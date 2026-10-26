import React from 'react';
import { render } from '@testing-library/react-native';
import Divider from '../src/components/Divider';
import { describe, expect, it } from '@jest/globals';

describe('Divider', () => {
  it('renders a plain hairline by default', () => {
    const { toJSON } = render(<Divider />);
    const tree = toJSON();
    expect(tree).toBeTruthy();
    // plain mode renders a single View, not the three-part ornament row
    expect(Array.isArray(tree)).toBe(false);
  });

  it('renders the ornamented variant when ornament is true', () => {
    const { toJSON } = render(<Divider ornament />);
    const tree: any = toJSON();
    // ornament mode is a row containing line / diamond / line children
    expect(tree.children?.length).toBe(3);
  });
});
