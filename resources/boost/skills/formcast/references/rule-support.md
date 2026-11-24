# Formcast Rule Support Matrix

Legend: **Client (Zod)** · **Server-only (Precognition / submit)** · **Partial (falls back to server when complex)**

| Category | Rules | Target |
| :--- | :--- | :--- |
| Presence | `required`, `filled`, `present`, `nullable`, `sometimes`, `missing`, `prohibited` | Client |
| Conditional presence | `required_if`, `required_unless`, `required_if_accepted`, `required_with`, `required_with_all`, `required_without`, `required_without_all`, `prohibited_if`, `prohibited_unless`, `missing_if` | Client (`superRefine`) |
| Types | `string`, `integer`, `numeric`, `boolean`, `array`, `list`, `decimal`, `json` | Client |
| Size (type-aware) | `min`, `max`, `size`, `between` (string = chars, numeric = value, array = count, file = KB) | Client |
| Digits | `digits`, `digits_between`, `min_digits`, `max_digits`, `multiple_of` | Client |
| Format | `email` (rfc/filter), `url`, `uuid`, `ulid`, `ip`, `ipv4`, `ipv6`, `mac_address`, `hex_color`, `alpha`, `alpha_num`, `alpha_dash`, `ascii`, `lowercase`, `uppercase`, `starts_with`, `ends_with`, `doesnt_start_with`, `doesnt_end_with` | Client |
| Regex | `regex`, `not_regex` | Partial (PCRE -> JS; unsafe patterns -> Server) |
| Sets | `in`, `not_in`, `Rule::in()`, `Rule::notIn()`, `Rule::enum()` (incl. `only` / `except`) | Client |
| Cross-field | `confirmed`, `same`, `different`, `gt`, `gte`, `lt`, `lte`, `in_array` | Client (`superRefine`) |
| Dates | `date`, `date_format`, `date_equals`, `after`, `after_or_equal`, `before`, `before_or_equal` | Client (literal dates, field refs, and runtime `today`/`tomorrow`/`yesterday`/`now`) |
| Acceptance | `accepted`, `accepted_if`, `declined`, `declined_if` | Client |
| Files | `file`, `image`, `mimes`, `mimetypes`, `extensions`, `File::types()->max()` | Partial (`dimensions` & `encoding` -> Server) |
| Password | `Password::min()->max()->letters()->mixedCase()->numbers()->symbols()` | Partial (`uncompromised()` -> Server) |
| Database | `unique`, `exists` | Server-only |
| Auth | `current_password` | Server-only |
| Network | `email:dns`, `email:spoof`, `active_url` | Server-only |
| Custom | Closures, `ValidationRule` objects, `Rule::when()` with callables | Server-only (unless implementing `CastsToZod` or `Formcast::extend()`) |
| Meta | `bail`, `exclude`, `exclude_if`, `exclude_unless`, `exclude_with`, `exclude_without` | Meta |
