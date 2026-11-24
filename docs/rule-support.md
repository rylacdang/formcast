# Rule Support Matrix

Legend: ✅ Client (Zod) · 🌐 Server-only (Precognition / submit) · ⚠️ Partial (falls back to server when complex)

| Category | Rules | Target |
| :--- | :--- | :---: |
| Presence | `required`, `filled`, `present`, `nullable`, `sometimes`, `missing`, `prohibited` | ✅ |
| Conditional presence | `required_if`, `required_unless`, `required_if_accepted`, `required_with`, `required_with_all`, `required_without`, `required_without_all`, `prohibited_if`, `prohibited_unless`, `missing_if` | ✅ |
| Types | `string`, `integer`, `numeric`, `boolean`, `array`, `list`, `decimal`, `json` | ✅ |
| Size (type-aware) | `min`, `max`, `size`, `between` (string = chars, numeric = value, array = count, file = KB) | ✅ |
| Digits | `digits`, `digits_between`, `min_digits`, `max_digits`, `multiple_of` | ✅ |
| Format | `email` (rfc/filter), `url`, `uuid`, `ulid`, `ip`, `ipv4`, `ipv6`, `mac_address`, `hex_color`, `alpha`, `alpha_num`, `alpha_dash`, `ascii`, `lowercase`, `uppercase`, `starts_with`, `ends_with`, `doesnt_start_with`, `doesnt_end_with` | ✅ |
| Regex | `regex`, `not_regex` (PCRE → JS translation; unsafe PCRE constructs → 🌐) | ⚠️ |
| Sets | `in`, `not_in`, `Rule::in()`, `Rule::notIn()`, `Rule::enum()` (incl. `only` / `except`) | ✅ |
| Cross-field | `confirmed`, `same`, `different`, `gt`, `gte`, `lt`, `lte`, `in_array` | ✅ |
| Dates | `date`, `date_format`, `date_equals`, `after`, `after_or_equal`, `before`, `before_or_equal` (literals, field refs, and runtime `today`/`tomorrow`/`yesterday`/`now`) | ✅ |
| Acceptance | `accepted`, `accepted_if`, `declined`, `declined_if` | ✅ |
| Files | `file`, `image`, `mimes`, `mimetypes`, `extensions`, `File::types()->max()` (`dimensions` & `encoding` → 🌐) | ⚠️ |
| Password | `Password::min()->max()->letters()->mixedCase()->numbers()->symbols()` (`uncompromised()` → 🌐) | ⚠️ |
| Database | `unique`, `exists` | 🌐 |
| Auth | `current_password` | 🌐 |
| Network | `email:dns`, `email:spoof`, `active_url` | 🌐 |
| Custom | Closures, `ValidationRule` objects, `Rule::when()` with callables (unless `CastsToZod` / `Formcast::extend()`) | 🌐 |
| Meta | `bail`, `exclude`, `exclude_if`, `exclude_unless`, `exclude_with`, `exclude_without` | ✅ |
