# Post Template — User Manual

The Post Template controls how LynxJournal turns your saved links into a
published roundup post. You'll find it under **LynxJournal → Post Template**
in the WordPress admin menu. Editing the template requires the `edit_posts`
capability.

## Overview

The template is plain text written in Markdown, with special `[token]`
placeholders that get replaced with real data (post title, links,
categories, tags, etc.) when a roundup is generated. As you type, a live
preview below the editor shows exactly what the rendered post will look
like, using sample data.

## The editor

- A toolbar above the text area provides quick formatting: **Undo/Redo**,
  **Bold**, *Italic*, <u>Underline</u>, a **Heading** dropdown (H1–H6),
  **Bullet list**, **Numbered list**, **Indent**/**Outdent**, and
  **Horizontal rule**. Each inserts the corresponding Markdown syntax
  (`**bold**`, `*italic*`, `<u>...</u>`, `# `, `- `, `1. `, `---`) at the
  cursor or around the current selection.
- Toolbar buttons highlight to show the active formatting at the cursor
  position (e.g. the Bold button lights up when your cursor is inside
  `**text**`).
- Ctrl/Cmd+Z and Ctrl/Cmd+Y (or Shift+Ctrl/Cmd+Z) undo/redo your edits.
- Blank lines you type are preserved and shown with a faint `¶` marker so
  spacing in your template is easy to see while editing (Markdown normally
  collapses blank lines).
- Leaving the page with unsaved changes prompts a browser confirmation.

### Indentation

Lines starting with 2+ spaces are treated as indented content and rendered
with matching left padding in the final post. Indented `- ` or `1. ` lines
directly following an unindented list item (no blank line between them)
become a genuine nested sub-list instead.

## Live preview

Below the editor, the **Preview** panel re-renders on every edit (after a
short pause while typing):

- **Desktop / Mobile** toggle — check how the rendered post looks at each
  width.
- **Live** status badge — dims briefly while a re-render is pending.
- Validation warnings appear above the preview if `[category_start]` /
  `[category_end]` or `[link_start]` / `[link_end]` tokens are unbalanced.

### Test publish

Opens a new browser tab showing the exact HTML (Gutenberg block markup)
that would be saved to a real post — useful for inspecting the output
without opening browser dev tools. Nothing is saved; this is read-only.

### Test post

Creates a real **draft** WordPress post from the current preview content
and opens it in the block editor for review. It is never published
automatically — you always land on a draft you can inspect, edit, or
discard. The draft title is taken from the first heading in the rendered
preview, prefixed with `[Test]`.

## Available tokens

Click **Available tokens** to expand a reference panel; clicking any token
button inserts it at the cursor. Tokens are grouped as follows:

### Structure
| Token | Meaning |
|---|---|
| `[category_start]` / `[category_end]` | Wraps the block of markup that repeats once per category in the roundup. |
| `[link_start]` / `[link_end]` | Wraps the block of markup that repeats once per link. Must be nested inside a category block (or used for a flat list of links). |

### Post
| Token | Meaning |
|---|---|
| `[title]` | Title of the roundup post. |
| `[date]` | Publish date. |
| `[author]` | Name of the post author. |
| `[site_name]` | Name of the site. |
| `[roundup_count]` | Number of roundups published so far. |

### Links
| Token | Meaning |
|---|---|
| `[link_count]` | Number of links in the roundup. |
| `[link]` | The link as a Markdown hyperlink (title + URL). |
| `[link_description]` | The link's saved description text. |
| `[link_domain]` | Domain of the URL (e.g. `example.com`). |
| `[link_date]` | Date the link was saved. |

### Categories
| Token | Meaning |
|---|---|
| `[category_name]` | The category's name. |
| `[category_link_count]` | Number of links in that category. |
| `[category_list]` | All categories, comma-separated. |

### Tags
| Token | Meaning |
|---|---|
| `[tags]` | The link's tags, comma-separated. |

## Example template

```
## [category_start][category_name][category_end]

[link_start]- [link] — [link_description][link_end]
```

This produces one `##` heading per category (the heading level you choose
here — H1 through H6 — is honored when the real roundup is published), each
followed by a bulleted list of that category's links.

## Saving

Click **Save Template** to persist your changes (stored in the
`lynxjournal_post_template` option). A "Template saved." notice confirms
the save.

## Notes on real publishing

- Real publishing (scheduled or manual) now fully honors your saved
  template — tokens, indentation, nested/ordered lists, bold/italic/
  underline, and horizontal rules all carry through to the actual
  Gutenberg blocks used when a roundup is published, matching what Test
  Publish/Test Post already preview. If no template is configured, a
  fixed built-in format is used instead (a `<h3>` heading per category
  followed by a bulleted link list).
- When a template is configured, category heading levels come from
  whatever real `#`–`######` markers actually appear in the rendered
  template output — not specifically scraped from the `[category_name]`
  line. (The no-template fixed format always uses H3.)
- Only a single blank line's worth of spacing survives in real output;
  runs of multiple consecutive blank lines collapse to normal Markdown
  paragraph spacing. This differs from the live in-editor preview, which
  marks every blank line with a visible `¶` for easier editing.
- Structured data (schema.org `ItemList` JSON-LD) is only added to the
  fixed, no-template format — it is not generated when a custom template
  is used, since a custom template has no equivalent fixed link-list
  structure to attach it to.
