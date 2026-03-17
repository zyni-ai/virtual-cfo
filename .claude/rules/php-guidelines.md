---
paths:
  - "**/*.php"
---

# PHP & Laravel Guidelines (adapted from Spatie)

## Type Declarations

- Use short nullable syntax: `?string` not `string|null`
- Always specify `void` return types when methods return nothing
- Use typed properties over docblocks
- Use constructor property promotion when all properties can be promoted

## Docblocks

- Don't add docblocks for fully type-hinted methods (unless a description is needed)
- Always import classnames in docblocks — never use fully qualified names
- Document iterables with generics: `@return Collection<int, User>`
- Use array shape notation for fixed keys, each key on its own line:
  ```php
  /** @return array{
      first: SomeClass,
      second: SomeClass,
  } */
  ```

## Control Flow

- **Happy path last** — handle error conditions first, success case last
- **Avoid else** — use early returns instead of nested conditions
- **Always use curly brackets** even for single statements
- **Avoid nested ternaries** — prefer `match` expressions, switch statements, or if/else chains for multiple conditions
- **Clarity over brevity** — explicit, readable code is better than compact one-liners that are hard to parse

## Strings

- Prefer string interpolation over concatenation: `"Hello {$name}"` not `'Hello ' . $name`

## Artisan Commands

- Put output BEFORE processing an item (easier to debug which item failed):
  ```php
  $this->info("Processing item id `{$item->id}`...");
  $this->processItem($item);
  ```
- Show a summary count at the end

## File Naming Conventions

| Type | Convention | Example |
|------|-----------|---------|
| Jobs | Action-based | `CreateUser`, `SendEmailNotification` |
| Events | Tense-based | `UserRegistering`, `UserRegistered` |
| Listeners | Action + `Listener` | `SendInvitationMailListener` |
| Commands | Action + `Command` | `PublishScheduledPostsCommand` |
| Mailables | Purpose + `Mail` | `AccountActivatedMail` |
| Resources | Model + `Resource` | `UserResource` |
