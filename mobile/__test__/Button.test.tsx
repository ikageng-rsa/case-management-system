import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import { describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';                            
import Button from '../src/components/Button';

describe('Button', () => {
  it('calls onPress when tapped', async () => {
    const onPress = jest.fn();
    const { getByRole } =await render(<Button label="Save" onPress={onPress} />);
    fireEvent.press(getByRole('button'));
    expect(onPress).toHaveBeenCalledTimes(1);
  });

  it('does not call onPress when disabled',async () => {
    const onPress = jest.fn();
    const { getByRole } =await render(<Button label="Save" onPress={onPress} disabled />);
    fireEvent.press(getByRole('button'));
    expect(onPress).not.toHaveBeenCalled();
  });
});
