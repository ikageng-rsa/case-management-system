import { useCallback, useState } from 'react';

type Validators<T> = Partial<{ [K in keyof T]: (value: T[K]) => string | undefined }>;

/**
 * Small form hook so screens like NewCase / AddNarration can move off ad-hoc
 * useState-per-field once they grow validation rules, without pulling in a
 * form library. Deliberately minimal — no schema, no async validation.
 *
 * const form = useForm({ clientName: '', matterType: '' }, {
 *   clientName: v => (isRequired(v) ? undefined : 'Required'),
 * });
 * form.values.clientName / form.setField('clientName', 'x') / form.errors.clientName
 */
export function useForm<T extends Record<string, any>>(initialValues: T, validators?: Validators<T>) {
  const [values, setValues] = useState<T>(initialValues);
  const [errors, setErrors] = useState<Partial<Record<keyof T, string>>>({});
  const [touched, setTouched] = useState<Partial<Record<keyof T, boolean>>>({});

  const setField = useCallback(
    <K extends keyof T>(key: K, value: T[K]) => {
      setValues(prev => ({ ...prev, [key]: value }));
      const validator = validators?.[key];
      if (validator) {
        setErrors(prev => ({ ...prev, [key]: validator(value) }));
      }
    },
    [validators],
  );

  const setTouchedField = useCallback((key: keyof T) => {
    setTouched(prev => ({ ...prev, [key]: true }));
  }, []);

  const validateAll = useCallback((): boolean => {
    if (!validators) {
      return true;
    }
    const nextErrors: Partial<Record<keyof T, string>> = {};
    (Object.keys(validators) as (keyof T)[]).forEach(key => {
      const validator = validators[key];
      if (validator) {
        const message = validator(values[key]);
        if (message) {
          nextErrors[key] = message;
        }
      }
    });
    setErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  }, [validators, values]);

  const reset = useCallback(() => {
    setValues(initialValues);
    setErrors({});
    setTouched({});
  }, [initialValues]);

  return { values, errors, touched, setField, setTouchedField, validateAll, reset };
}
