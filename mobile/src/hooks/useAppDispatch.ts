import { useDispatch, useSelector, TypedUseSelectorHook } from 'react-redux';
import { AppDispatch } from '@/store/store';
import { RootState } from '@/store/rootReducer';

export const useAppDispatch: () => AppDispatch = useDispatch;
export const useAppSelector: TypedUseSelectorHook<RootState> = useSelector;
