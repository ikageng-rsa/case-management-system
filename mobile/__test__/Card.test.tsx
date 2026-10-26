import React from 'react';
import { Text } from 'react-native';
import { render } from '@testing-library/react-native';
import Card from '../src/components/Card';
import { describe, expect, it } from '@jest/globals';

describe('Card', () => {
  it('renders its children',async () => {
    const { getByText } = await render(
      <Card>
        <Text>Case TM/LIT/014/2026</Text>
      </Card>,
    );
    expect(getByText('Case TM/LIT/014/2026')).toBeTruthy();
  });

  it('merges custom style with the default card style',async () => {
    const { getByTestId } =await render(
      <Card style={{ borderColor: '#8B2E2E' }}>
        <Text testID="child">content</Text>
      </Card>,
    );
    expect(getByTestId('child')).toBeTruthy();
  });
});
