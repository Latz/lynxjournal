import { createRoot } from '@wordpress/element';
import CategoriesApp from '../categories/App';
import TagsApp from '../tags/App';

const categoriesRoot = document.getElementById('lynxjournal-categories-root');
if (categoriesRoot) {
  createRoot(categoriesRoot).render(<CategoriesApp />);
}

const tagsRoot = document.getElementById('lynxjournal-tags-root');
if (tagsRoot) {
  createRoot(tagsRoot).render(<TagsApp />);
}
