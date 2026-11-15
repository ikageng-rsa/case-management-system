# React Native UI kit

The web theme (`backend/resources/css`) and `x-ui.*` components rebuilt for React Native.

## Setup

Copy the folder into the app and install the fonts:

```bash
cd mobile && npx expo install @expo-google-fonts/ibm-plex-sans @expo-google-fonts/ibm-plex-mono @expo-google-fonts/newsreader
```

Load the fonts and wrap the app once (e.g. `src/app/_layout.tsx`):

```tsx
import { useFonts, IBMPlexSans_400Regular, IBMPlexSans_500Medium, IBMPlexSans_600SemiBold } from '@expo-google-fonts/ibm-plex-sans';
import { IBMPlexMono_400Regular, IBMPlexMono_500Medium, IBMPlexMono_600SemiBold } from '@expo-google-fonts/ibm-plex-mono';
import { Newsreader_400Regular, Newsreader_600SemiBold } from '@expo-google-fonts/newsreader';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { ConfirmProvider, ThemeProvider, ToastProvider } from '@/ui';

const [loaded] = useFonts({
  'IBMPlexSans-Regular': IBMPlexSans_400Regular,
  'IBMPlexSans-Medium': IBMPlexSans_500Medium,
  'IBMPlexSans-SemiBold': IBMPlexSans_600SemiBold,
  'IBMPlexMono-Regular': IBMPlexMono_400Regular,
  'IBMPlexMono-Medium': IBMPlexMono_500Medium,
  'IBMPlexMono-SemiBold': IBMPlexMono_600SemiBold,
  'Newsreader-Regular': Newsreader_400Regular,
  'Newsreader-SemiBold': Newsreader_600SemiBold,
});

<SafeAreaProvider>
  <ThemeProvider mode="system">
    <ToastProvider>
      <ConfirmProvider>
        <Stack />
      </ConfirmProvider>
    </ToastProvider>
  </ThemeProvider>
</SafeAreaProvider>
```

## Theme

```tsx
import { StyleSheet } from 'react-native';
import { space, Theme, typography, useStyles, useTheme } from '@/ui';

const styles = useStyles(makeStyles);
const theme = useTheme(); // theme.colors.fg, theme.tones.danger.bg, theme.mode

const makeStyles = (t: Theme) =>
  StyleSheet.create({
    title: { ...typography.h2, color: t.colors.fg, marginBottom: space[16] },
  });
```

## Button

```tsx
<Button onPress={save}>Save</Button>
<Button variant="outline-secondary" size="sm" icon="plus" onPress={add}>Add party</Button>
<Button variant="danger" loading={saving} onPress={remove}>Remove</Button>
<Button variant="link" onPress={open}>View matter</Button>
<Button icon="more-horizontal" accessibilityLabel="More" onPress={menu} />
```

## Icon

Feather names from `@expo/vector-icons`.

```tsx
<Icon name="search" />
<Icon name="alert-triangle" size="sm" color={theme.tones.danger.bg} />
```

## Input

```tsx
<Input label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" error={errors.email} />
<Input label="Notes" multiline hint="Visible to the matter team" value={notes} onChangeText={setNotes} />
<Input label="Password" tone="dark" secureTextEntry value={password} onChangeText={setPassword} />
```

## Select

```tsx
<Select
  label="Matter type"
  options={[{ label: 'Litigation', value: 1 }, { label: 'Conveyancing', value: 2 }]}
  value={typeId}
  onChange={setTypeId}
  error={errors.matter_type_id}
/>
```

## Field

Wraps your own control with the same label, hint and error.

```tsx
<Field label="Consent" hint="Required before opening a matter">
  <Switch value={consent} onValueChange={setConsent} />
</Field>
```

## Card and StatCard

```tsx
<Card><Text>Anything</Text></Card>
<StatCard label="Open matters" value={128} delta="+4" />
<StatCard label="Overdue" value={7} delta="+2" tone="danger" />
```

## StageBadge

Tones: `neutral`, `info`, `accent`, `success`, `danger`.

```tsx
<StageBadge tone="info">Pleadings</StageBadge>
```

## LogoMark

```tsx
<LogoMark initial="A" />
<LogoMark initial="A" size="sm" />
```

## SearchField and Header

```tsx
<SearchField value={query} onChangeText={setQuery} onSubmitEditing={runSearch} />

<Header leading={<Button variant="link" icon="menu" accessibilityLabel="Menu" onPress={openDrawer} />}
        searchProps={{ value: query, onChangeText: setQuery }}>
  <Button variant="link" icon="bell" accessibilityLabel="Notifications" onPress={openNotifications} />
</Header>
```

## Sidebar, NavSection and NavItem

Use as drawer content; it always renders dark.

```tsx
<Drawer
  screenOptions={{ drawerStyle: { width: layout.sidebarWidth } }}
  drawerContent={({ navigation, state }) => (
    <Sidebar firm={firm.name} tagline="Case management" user={user.name} role={user.role} initials={user.initials}
             onAccountPress={() => navigation.navigate('account')}>
      <NavSection>Work</NavSection>
      <NavItem label="Matters" icon="briefcase" count={128} active={state.index === 0} onPress={() => navigation.navigate('matters')} />
      <NavItem label="Clients" icon="users" onPress={() => navigation.navigate('clients')} />
    </Sidebar>
  )}
/>
```

## Sheet

```tsx
const [open, setOpen] = useState(false);

<Sheet visible={open} onClose={() => setOpen(false)} title="Add rate" lede="Charged per hour" icon="plus"
       footer={<Button onPress={submit}>Save rate</Button>}>
  <Input label="Rate" keyboardType="decimal-pad" value={rate} onChangeText={setRate} />
</Sheet>
```

## Confirm

```tsx
const confirm = useConfirm();

if (await confirm({ title: 'Remove from project', message: 'Remove Sipho from this project?', actionLabel: 'Remove', icon: 'user-minus' })) {
  remove();
}

const answer = await confirm({ title: 'Void invoice', actionLabel: 'Void', promptLabel: 'Why is it being voided?' });
if (typeof answer === 'object') voidInvoice(answer.reason);

// Outside React
confirmAction({ message: 'Discard this draft?', tone: 'primary' });
```

## Toasts

Success goes after 5s; problems stay until dismissed.

```tsx
const toast = useToast();
toast({ message: 'Matter opened.' });
toast({ message: 'That could not be saved.', tone: 'danger' });

// Outside React, e.g. an axios interceptor
import { toast } from '@/ui';
toast({ message: 'You are offline.', tone: 'warning', life: 3000 });
```

## MatterPicker

`search` gets the typed text (empty on focus). The parent ScrollView needs `keyboardShouldPersistTaps="handled"`.

```tsx
<MatterPicker
  label="Matter"
  value={matter}
  onChange={setMatter}
  search={(q) => api.get('/matters/search', { params: { q, scope: 'fileable' } }).then((r) => r.data)}
  error={errors.matter_id}
/>
```

## RevealValue

Hides itself again after `seconds`. Shows a danger toast if the request fails.

```tsx
<RevealValue masked="•••• •••• 4821" reveal={() => api.post(`/clients/${id}/reveal`, { target: 'id_number' }).then((r) => r.data.value)} />
```
