import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const CATEGORIES_PATH = '/lynxjournal/v1/categories';
const COUNTS_PATH = '/lynxjournal/v1/categories/counts';

/**
 * Renders one editable category row, toggling between display and inline-edit modes.
 *
 * @param {object}   props
 * @param {object}   props.category  - Category term ({ id, name, slug, description }).
 * @param {number}   props.count     - Number of links in this category.
 * @param {Function} props.onUpdated - Called with the updated category after a successful save.
 * @param {Function} props.onDeleted - Called with the category id after a successful delete.
 * @returns {JSX.Element}
 */
function CategoryRow({ category, count, onUpdated, onDeleted }) {
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(category);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  /** @listens click Enters inline-edit mode, resetting the draft to the current category values. */
  function handleEdit() {
    setDraft(category);
    setError('');
    setEditing(true);
  }

  /** @listens click Discards edits and returns to display mode. */
  function handleCancel() {
    setEditing(false);
    setError('');
  }

  /** @listens click Saves the edited category via the REST API. */
  async function handleSave() {
    const name = draft.name.trim();
    if (!name) {
      setError(__('Name is required.', 'lynx-journal'));
      return;
    }
    setSaving(true);
    setError('');
    try {
      const updated = await apiFetch({
        path: `${CATEGORIES_PATH}/${category.id}`,
        method: 'POST',
        data: { name, description: draft.description, slug: draft.slug },
      });
      onUpdated(updated);
      setEditing(false);
    } catch (err) {
      setError(err?.message || __('Save failed.', 'lynx-journal'));
    } finally {
      setSaving(false);
    }
  }

  /** @listens click Confirms and deletes the category via the REST API. */
  async function handleDelete() {
    /* translators: %s: category name */
    const deleteConfirm = sprintf(__("Delete '%s'?", 'lynx-journal'), category.name);
    const message = count > 0
      ? `${deleteConfirm} ${count} ${count === 1 ? __('link will become uncategorized.', 'lynx-journal') : __('links will become uncategorized.', 'lynx-journal')}`
      : deleteConfirm;
    if (!window.confirm(message)) return;

    try {
      await apiFetch({ path: `${CATEGORIES_PATH}/${category.id}`, method: 'DELETE' });
      onDeleted(category.id);
    } catch (err) {
      setError(err?.message || __('Could not delete category.', 'lynx-journal'));
    }
  }

  if (editing) {
    return (
      <tr className="lynxjournal-cat-row lynxjournal-cat-editing">
        <td className="lynxjournal-cat-cell-name">
          <input
            type="text"
            className="lynxjournal-cat-inline-input"
            value={draft.name}
            onChange={(e) => setDraft((d) => ({ ...d, name: e.target.value }))}
            autoFocus
          />
        </td>
        <td className="lynxjournal-cat-cell-description lynxjournal-cat-desc">
          <textarea
            className="lynxjournal-cat-inline-input"
            rows={2}
            value={draft.description}
            placeholder={__('Description (optional)', 'lynx-journal')}
            onChange={(e) => setDraft((d) => ({ ...d, description: e.target.value }))}
          />
        </td>
        <td className="lynxjournal-cat-cell-slug">
          <input
            type="text"
            className="lynxjournal-cat-inline-input lynxjournal-cat-inline-slug"
            value={draft.slug}
            placeholder={__('Leave blank to keep current', 'lynx-journal')}
            onChange={(e) => setDraft((d) => ({ ...d, slug: e.target.value }))}
          />
        </td>
        <td className="lynxjournal-cat-count-col">{count}</td>
        <td className="lynxjournal-cat-actions">
          <button
            type="button"
            className="button button-primary lynxjournal-cat-save-btn"
            onClick={handleSave}
            disabled={saving}
          >
            {saving ? __('Saving…', 'lynx-journal') : __('Save', 'lynx-journal')}
          </button>
          {' '}
          <button type="button" className="button-link lynxjournal-cat-cancel-btn" onClick={handleCancel}>
            {__('Cancel', 'lynx-journal')}
          </button>
          {error && <span className="lynxjournal-cat-inline-error">{error}</span>}
        </td>
      </tr>
    );
  }

  return (
    <tr className="lynxjournal-cat-row">
      <td className="lynxjournal-cat-cell-name"><strong>{category.name}</strong></td>
      <td className="lynxjournal-cat-cell-description lynxjournal-cat-desc">{category.description}</td>
      <td className="lynxjournal-cat-cell-slug"><code>{category.slug}</code></td>
      <td className="lynxjournal-cat-count-col">{count}</td>
      <td className="lynxjournal-cat-actions">
        <button type="button" className="button-link lynxjournal-cat-edit-btn" onClick={handleEdit}>
          {__('Edit', 'lynx-journal')}
        </button>
        &nbsp;|&nbsp;
        <button type="button" className="button-link lynxjournal-cat-delete-btn" onClick={handleDelete}>
          {__('Delete', 'lynx-journal')}
        </button>
        {error && <span className="lynxjournal-cat-inline-error">{error}</span>}
      </td>
    </tr>
  );
}

/**
 * Renders the categories table, or an empty-state message when there are none.
 *
 * @param {object}   props
 * @param {object[]} props.categories - Category terms.
 * @param {object}   props.counts     - Map of term_id => link count.
 * @param {Function} props.onUpdated  - Passed through to each CategoryRow.
 * @param {Function} props.onDeleted  - Passed through to each CategoryRow.
 * @returns {JSX.Element}
 */
function CategoryTable({ categories, counts, onUpdated, onDeleted }) {
  if (categories.length === 0) {
    return <p>{__('No categories yet. Use the form to add your first category.', 'lynx-journal')}</p>;
  }
  return (
    <table className="wp-list-table widefat striped lynxjournal-cat-table">
      <thead>
        <tr>
          <th>{__('Name', 'lynx-journal')}</th>
          <th>{__('Description', 'lynx-journal')}</th>
          <th>{__('Slug', 'lynx-journal')}</th>
          <th className="lynxjournal-cat-count-col">{__('Links', 'lynx-journal')}</th>
          <th>{__('Actions', 'lynx-journal')}</th>
        </tr>
      </thead>
      <tbody>
        {categories.map((category) => (
          <CategoryRow
            key={category.id}
            category={category}
            count={counts[category.id] ?? 0}
            onUpdated={onUpdated}
            onDeleted={onDeleted}
          />
        ))}
      </tbody>
    </table>
  );
}

/**
 * Renders the "Add New Category" form.
 *
 * @param {object}   props
 * @param {Function} props.onCreated - Called with the newly created category on success.
 * @returns {JSX.Element}
 */
function AddCategoryForm({ onCreated }) {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  /** @listens submit Creates a new category via the REST API. */
  async function handleSubmit(e) {
    e.preventDefault();
    if (!name.trim()) {
      setError(__('Category name is required.', 'lynx-journal'));
      return;
    }
    setSubmitting(true);
    setError('');
    try {
      const created = await apiFetch({ path: CATEGORIES_PATH, method: 'POST', data: { name: name.trim(), description } });
      onCreated(created);
      setName('');
      setDescription('');
    } catch (err) {
      setError(err?.message || __('Save failed.', 'lynx-journal'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="postbox">
      <div className="postbox-header">
        <h2 className="hndle">{__('Add New Category', 'lynx-journal')}</h2>
      </div>
      <div className="inside">
        <form onSubmit={handleSubmit}>
          <p>
            <label htmlFor="cat_name"><strong>{__('Name', 'lynx-journal')} *</strong></label><br />
            <input
              type="text"
              id="cat_name"
              className="regular-text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
            />
          </p>
          <p>
            <label htmlFor="cat_description">
              <strong>{__('Description', 'lynx-journal')}</strong>
              {' '}
              <span className="lynxjournal-optional">{__('(optional)', 'lynx-journal')}</span>
            </label><br />
            <textarea
              id="cat_description"
              className="regular-text"
              rows={3}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </p>
          {error && <p className="lynxjournal-cat-inline-error">{error}</p>}
          <p>
            <button type="submit" className="button button-primary" disabled={submitting}>
              {__('Add Category', 'lynx-journal')}
            </button>
          </p>
        </form>
      </div>
    </div>
  );
}

export default function App() {
  const [categories, setCategories] = useState([]);
  const [counts, setCounts] = useState({});
  const [loading, setLoading] = useState(true);
  const [notice, setNotice] = useState(null);

  const loadData = useCallback(() => {
    setLoading(true);
    Promise.all([
      apiFetch({ path: CATEGORIES_PATH }),
      apiFetch({ path: COUNTS_PATH }),
    ])
      .then(([categoryList, countMap]) => {
        setCategories(categoryList);
        setCounts(countMap);
      })
      .catch(() => setNotice({ status: 'error', message: __('Failed to load categories.', 'lynx-journal') }))
      .finally(() => setLoading(false));
  }, []);

  useEffect(loadData, [loadData]);

  /** @param {object} updated Updated category, merged into the categories list. */
  function handleUpdated(updated) {
    setCategories((list) => list.map((c) => (c.id === updated.id ? updated : c)));
  }

  /** @param {number} id Deleted category's term id, removed from the categories list and counts map. */
  function handleDeleted(id) {
    setCategories((list) => list.filter((c) => c.id !== id));
    setCounts(({ [id]: _removed, ...rest }) => rest);
  }

  /** @param {object} created Newly created category, appended to the categories list. */
  function handleCreated(created) {
    setCategories((list) => [...list, created]);
    setCounts((c) => ({ ...c, [created.id]: 0 }));
  }

  if (loading) {
    return null;
  }

  return (
    <>
      {notice && (
        <Notice status={notice.status} onRemove={() => setNotice(null)} isDismissible>
          {notice.message}
        </Notice>
      )}
      <div className="metabox-holder lynxjournal-dashboard">
        <div id="lynxjournal-postbox-container-1" className="postbox-container">
          <CategoryTable categories={categories} counts={counts} onUpdated={handleUpdated} onDeleted={handleDeleted} />
        </div>
        <div id="lynxjournal-postbox-container-2" className="postbox-container">
          <AddCategoryForm onCreated={handleCreated} />
        </div>
      </div>
    </>
  );
}
