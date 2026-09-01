# Legal Case Management Platform — Mobile (React Native) Conventions

TypeScript/React Native style, naming, and testing conventions for `/mobile` in this monorepo.
`/backend` has its own conventions — don't assume anything below applies there.

---

## 1. Stack

- React Native CLI (not Expo), TypeScript.
- Navigation: React Navigation.
- Server state: TanStack Query, consuming `/backend`'s REST API directly — no local
  duplication of data the API already owns.
- Local/UI state: React state (`useState`/`useReducer`) and context where genuinely shared;
  no Redux or other global store unless a concrete need for one shows up.
- Styling: `StyleSheet.create`, no CSS-in-JS library.
- Testing: Jest + React Native Testing Library.
- Linting/formatting: the ESLint + Prettier config from the React Native CLI template,
  unmodified unless the team agrees on a specific rule change.

## 2. Naming Conventions

`case` is a reserved word in JavaScript/TypeScript, same as PHP — but TypeScript is
case-sensitive, so `Case` (capital C) doesn't actually collide with it the way it does in
PHP. The type is still named `LegalCase`, matching the backend model, purely so the same
entity has the same name in both codebases.

| Type | Convention | Example |
|---|---|---|
| Screen component | PascalCase + `Screen` suffix | `CasesListScreen`, `CaseDetailScreen` |
| Reusable component | PascalCase, no suffix | `NarrationCard`, `DiaryEntryRow` |
| Hook | camelCase, `use` prefix | `useCases`, `useDiaryEntries` |
| API client function | camelCase, verb first | `fetchCases()`, `createNarration()` |
| Type/interface | PascalCase, matches backend model | `LegalCase`, `Narration`, `DiaryEntry`, `CaseDocument`, `User` |
| Navigation route name | PascalCase, matches screen minus `Screen` | `CasesList`, `CaseDetail` |
| File name | Matches the default export | `CaseDetailScreen.tsx`, `useDiaryEntries.ts` |

## 3. TypeScript Style

Strict mode on. No `any` — if the shape is genuinely unknown, use `unknown` and narrow it.

```ts
interface LegalCase {
  id: number;
  reference: string;
  clientName: string;
  matterType: MatterType;
  status: 'open' | 'closed';
}
```

Functional components only, typed props via an interface, not an inline object type.

```tsx
interface NarrationCardProps {
  narration: Narration;
  onPress?: (narration: Narration) => void;
}

export function NarrationCard({ narration, onPress }: NarrationCardProps) {
  // ...
}
```

Named exports for components and hooks — not default exports. Keeps renames and
find-references reliable across the codebase.

## 4. Components

One component per file. A screen composes smaller components rather than growing a single
large render function — a `CaseDetailScreen` built from `CaseHeader`, `NarrationList`, and
`DiaryPanel` is preferred over one file that renders all three inline.

## 5. Navigation

A single stack navigator per major flow, with tab navigation as the top-level shell (Cases,
Diary, and whatever else the role in question needs). Route params are typed:

```ts
type RootStackParamList = {
  CasesList: undefined;
  CaseDetail: { caseId: number };
};
```

## 6. State & Data Fetching

Any data owned by the API (cases, narrations, diary entries, documents) is fetched and cached
via TanStack Query — not copied into component state or a global store. Local component state
is reserved for things the API doesn't need to know about (a form draft, whether a modal is
open).

```ts
export function useCases() {
  return useQuery({
    queryKey: ['cases'],
    queryFn: fetchCases,
  });
}
```

## 7. API Client

A thin, typed wrapper around `fetch`, mirroring the backend's `/api/v1` resource names
one-to-one — `fetchCases()` calls `GET /api/v1/cases`, `createNarration()` calls
`POST /api/v1/cases/{id}/narrations`, and so on. The authentication token (Sanctum) is stored
via `react-native-keychain`, not `AsyncStorage` — `AsyncStorage` is unencrypted, and this token
guards access to client case data.

## 8. Styling

`StyleSheet.create` at the bottom of the component file that uses it. No inline style objects
except a one-off numeric override on a shared style.

```tsx
const styles = StyleSheet.create({
  card: {
    padding: 12,
    borderRadius: 8,
  },
});
```

## 9. Control Flow

Guard clauses over nested conditionals; no `else` where an early return works.

```ts
function formatDueDate(entry: DiaryEntry): string {
  if (!entry.dueDate) {
    return 'No date set';
  }

  return isOverdue(entry.dueDate) ? `Overdue — ${entry.dueDate}` : entry.dueDate;
}
```

## 10. Testing

Jest + React Native Testing Library. Every feature includes:

- **Unit tests** for hooks and utility functions in isolation, API calls mocked.
- **One component/integration test per screen**, rendering it and interacting with it the way
  a user would (`fireEvent`/`userEvent`), asserting on what's rendered.

Test files are colocated with the file they test: `useDiaryEntries.test.ts` next to
`useDiaryEntries.ts`, `CaseDetailScreen.test.tsx` next to `CaseDetailScreen.tsx`.

```tsx
it('shows the overdue badge when a diary entry is past its due date', () => {
  render(<DiaryEntryRow entry={overdueEntryFixture} />);
  expect(screen.getByText('Overdue')).toBeVisible();
});
```

## 11. Code Organization

```
mobile/
  src/
    screens/       (CasesListScreen.tsx, CaseDetailScreen.tsx, DiaryScreen.tsx, ...)
    components/    (NarrationCard.tsx, DiaryEntryRow.tsx, ...)
    navigation/     (AppNavigator.tsx)
    api/            (client.ts, cases.ts, narrations.ts, diaryEntries.ts)
    hooks/          (useCases.ts, useDiaryEntries.ts, ...)
    types/          (LegalCase.ts, Narration.ts, DiaryEntry.ts, ...)
  App.tsx
```

No feature-based nesting beyond this — the same reasoning as `/backend`'s structure applies:
this is a small app, and a flat, resource-grouped layout is easier to navigate than a deep
folder hierarchy built for a scale the project isn't at.

## 12. Git & Commits

Same branching model, commit conventions, and PR process as the rest of the repo — see the
root `CONTRIBUTING.md`. Nothing mobile-specific to add there.
