import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';
import Input from '../src/components/Input';
import { describe, expect, it } from '@jest/globals';

describe('Input', () => {
  it('renders the label', () => {
    const { getByText } = render(<Input label="Email" value="" onChangeText={() => {}} />);
    expect(getByText('Email')).toBeTruthy();
  });

  it('displays the current value', () => {
    const { getByDisplayValue } = render(
      <Input label="Email" value="ronewa@firm.co.za" onChangeText={() => {}} />,
    );
    expect(getByDisplayValue('ronewa@firm.co.za')).toBeTruthy();
  });

  it('calls onChangeText when the user types', () => {
    const onChangeText = jest.fn();
    const { getByDisplayValue } = render(<Input label="Email" value="" onChangeText={onChangeText} />);

    fireEvent.changeText(getByDisplayValue(''), 'new@value.com');

    expect(onChangeText).toHaveBeenCalledWith('new@value.com');
  });

  it('shows an error message when provided', () => {
    const { getByText } = render(
      <Input label="Email" value="" onChangeText={() => {}} error="Email is required" />,
    );
    expect(getByText('Email is required')).toBeTruthy();
  });

  it('does not render an error message when none is provided', () => {
    const { queryByText } = render(<Input label="Email" value="" onChangeText={() => {}} />);
    expect(queryByText('Email is required')).toBeNull();
  });
});
